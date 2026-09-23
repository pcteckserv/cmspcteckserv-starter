<?php

namespace Pcteckserv\CmsCore\Updates;

use Symfony\Component\Process\Process;

class PackageUpdater
{
    public function update(string $package): UpdateResult
    {
        $installedPackage = $this->installedComposerPackage($package);
        $previousVersion = $installedPackage['version'] ?? null;
        $availableVersion = $this->availableVersion($package);

        if (($installedPackage['dist']['type'] ?? null) === 'path'
            && is_string($previousVersion)
            && is_string($availableVersion)
            && version_compare($this->normalizeVersion($availableVersion), $this->normalizeVersion($previousVersion), '>')
        ) {
            $this->updateComposerPathRepositoryVersion($package, $availableVersion);
        }

        $composer = $this->run([$this->composerExecutable(), 'update', $package, '--with-dependencies']);

        if (! $composer->isSuccessful()) {
            return new UpdateResult(false, 'Composer falhou: '.$this->processOutput($composer));
        }

        $updatedPackage = $this->installedComposerPackage($package);
        $updatedVersion = $updatedPackage['version'] ?? null;

        if (is_string($previousVersion) && $updatedVersion === $previousVersion
            && ($updatedPackage['source']['reference'] ?? $updatedPackage['dist']['reference'] ?? null)
                === ($installedPackage['source']['reference'] ?? $installedPackage['dist']['reference'] ?? null)) {
            $repositoryHint = ($installedPackage['dist']['type'] ?? null) === 'path'
                ? ' A package continua instalada a partir do repositório local path '.($installedPackage['dist']['url'] ?? 'sem caminho').'.'
                : '';

            return new UpdateResult(false, 'O Composer terminou sem alterar a versão instalada (continua em '.$previousVersion.'). Verifique se o composer.json permite instalar a versão disponível.'.$repositoryHint);
        }

        if (is_string($availableVersion) && is_string($updatedVersion)
            && version_compare($this->normalizeVersion($updatedVersion), $this->normalizeVersion($availableVersion), '<')) {
            return new UpdateResult(false, 'A versão instalada ('.$updatedVersion.') continua abaixo da versão disponível ('.$availableVersion.'). Verifique as constraints do composer.json.');
        }

        $migrate = $this->run([PHP_BINARY, 'artisan', 'migrate', '--force']);

        if (! $migrate->isSuccessful()) {
            return new UpdateResult(false, 'Migrations falharam: '.$this->processOutput($migrate));
        }

        $cache = $this->run([PHP_BINARY, 'artisan', 'optimize:clear']);

        if (! $cache->isSuccessful()) {
            return new UpdateResult(false, 'Limpeza de cache falhou: '.$this->processOutput($cache));
        }

        return new UpdateResult(true, 'Atualização concluída com sucesso.');
    }

    /**
     * @param array<int, string> $command
     */
    private function run(array $command): Process
    {
        $process = new Process($command, base_path());
        $process->setTimeout(300);
        $process->setEnv($this->environment());
        $process->run();

        return $process;
    }

    private function composerExecutable(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'composer.bat' : 'composer';
    }

    private function processOutput(Process $process): string
    {
        $output = trim($process->getErrorOutput()) ?: trim($process->getOutput());

        if ($output === '') {
            return 'sem detalhe devolvido pelo processo.';
        }

        return mb_strimwidth($output, 0, 800, '...');
    }

    /**
     * @return array<string, mixed>
     */
    private function installedComposerPackage(string $package): array
    {
        $process = $this->run([$this->composerExecutable(), 'show', $package, '--format=json']);

        if (! $process->isSuccessful()) {
            return [];
        }

        $packageData = json_decode($process->getOutput(), true);

        if (! is_array($packageData)) {
            return [];
        }

        $packageData['version'] ??= $packageData['versions'][0] ?? null;

        return $packageData;
    }

    private function availableVersion(string $package): ?string
    {
        $storedVersion = \Illuminate\Support\Facades\DB::table('cms_installed_packages')
            ->where('name', $package)
            ->value('available_version');

        if (is_string($storedVersion) && $storedVersion !== '') {
            return $storedVersion;
        }

        return app(GitTagUpdateChecker::class)->latestVersion($package);
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim($version, 'v');
    }

    private function updateComposerPathRepositoryVersion(string $package, string $availableVersion): bool
    {
        $composerPath = base_path('composer.json');

        if (! is_file($composerPath) || ! is_readable($composerPath) || ! is_writable($composerPath)) {
            return false;
        }

        $manifest = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($manifest) || ! isset($manifest['repositories']) || ! is_array($manifest['repositories'])) {
            return false;
        }

        $changed = false;

        foreach ($manifest['repositories'] as &$repository) {
            if (! is_array($repository) || ($repository['type'] ?? null) !== 'path') {
                continue;
            }

            $configuredVersion = $repository['options']['versions'][$package] ?? null;

            if ($configuredVersion !== null && $configuredVersion !== $this->normalizeVersion($availableVersion)) {
                $repository['options']['versions'][$package] = $this->normalizeVersion($availableVersion);
                $changed = true;
            }
        }

        unset($repository);

        if (! $changed) {
            return false;
        }

        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($encoded)) {
            return false;
        }

        file_put_contents($composerPath, $encoded.PHP_EOL);

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        $this->ensureComposerDirectories();

        $environment = [
            'PATH' => $this->pathWithPhp(),
            'Path' => $this->pathWithPhp(),
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: getenv('SystemRoot') ?: 'C:\\Windows',
            'COMSPEC' => getenv('COMSPEC') ?: 'C:\\Windows\\System32\\cmd.exe',
            'PATHEXT' => getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD',
            'COMPOSER_HOME' => storage_path('framework/cache/composer'),
            'APPDATA' => storage_path('framework/cache/composer'),
            'TMP' => storage_path('framework/cache/composer-tmp'),
            'TEMP' => storage_path('framework/cache/composer-tmp'),
            'GIT_CONFIG_GLOBAL' => $this->gitConfigPath(),
            'GIT_TERMINAL_PROMPT' => '0',
        ];

        $token = config('cms-core.updates.github_token');

        if (! is_string($token) || $token === '') {
            return $environment;
        }

        return $environment + [
            'COMPOSER_AUTH' => json_encode([
                'github-oauth' => [
                    'github.com' => $token,
                ],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private function pathWithPhp(): string
    {
        $path = getenv('PATH') ?: getenv('Path') ?: '';
        $phpDirectory = dirname(PHP_BINARY);

        if (str_contains($path, $phpDirectory)) {
            return $path;
        }

        return $phpDirectory.PATH_SEPARATOR.$path;
    }

    private function ensureComposerDirectories(): void
    {
        foreach ([
            storage_path('framework/cache/composer'),
            storage_path('framework/cache/composer-tmp'),
        ] as $directory) {
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
        }

        $safeDirectory = str_replace('\\', '/', base_path());
        $gitConfig = "[safe]\n\tdirectory = {$safeDirectory}\n";

        $token = config('cms-core.updates.github_token');

        if (is_string($token) && $token !== '') {
            $authorization = base64_encode('x-access-token:'.$token);

            $gitConfig .= "[http \"https://github.com/\"]\n";
            $gitConfig .= "\textraheader = AUTHORIZATION: basic {$authorization}\n";
        }

        $gitConfigPath = $this->gitConfigPath();
        if (! is_file($gitConfigPath) || file_get_contents($gitConfigPath) !== $gitConfig) {
            file_put_contents($gitConfigPath, $gitConfig);
        }
    }

    private function gitConfigPath(): string
    {
        return storage_path('framework/cache/composer-gitconfig');
    }
}
