<?php

namespace App\Services;

use App\Services\Deployment\DatabaseSnapshot;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Pcteckserv\CmsCore\Models\InstalledPlugin;
use Phar;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

class DeploymentPackageBuilder
{
    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    /**
     * @return array{path:string,relative_path:string,size:int,created_at:string,warnings:array<int, string>}
     */
    public function build(array $components = ['base'], #[\SensitiveParameter] ?string $databasePassword = null): array
    {
        $originalLimit = (int) ini_get('max_execution_time');
        $timeout = (int) config('deploy.execution_timeout', 1800);

        // O limite do pedido tem de abranger o build e a criação dos ficheiros ZIP.
        if (! set_time_limit($timeout > 0 ? $timeout : 1800)) {
            throw new RuntimeException('Não foi possível configurar o tempo disponível para a compilação.');
        }

        try {
            return $this->buildPackage($components, $databasePassword);
        } finally {
            set_time_limit($originalLimit);
        }
    }

    /**
     * @return array{path:string,relative_path:string,size:int,created_at:string,warnings:array<int, string>}
     */
    private function buildPackage(array $components, #[\SensitiveParameter] ?string $databasePassword): array
    {
        $this->warnings = [];

        if ($components === [] || array_diff($components, ['base', 'media', 'database', 'plugins']) !== []) {
            throw new RuntimeException('Selecione opções de compilação válidas.');
        }

        if (! extension_loaded('zip')) {
            throw new RuntimeException('A extensão PHP ZipArchive não está disponível.');
        }

        $disk = Storage::disk((string) config('deploy.output_disk', 'local'));
        $outputDirectory = trim((string) config('deploy.output_directory', 'deploy'), '/');
        $packageName = pathinfo((string) config('deploy.package_name', 'cmspcteckserv-deploy.zip'), PATHINFO_FILENAME).'-'.bin2hex(random_bytes(8)).'.zip';
        $applicationArchiveName = (string) config('deploy.application_archive_name', 'application.zip');
        $installerName = (string) config('deploy.installer_name', 'installer.php');

        $disk->makeDirectory($outputDirectory);

        $temporaryDirectory = $disk->path($outputDirectory.'/tmp-'.bin2hex(random_bytes(8)));
        $this->files->ensureDirectoryExists($temporaryDirectory);
        chmod($temporaryDirectory, 0700);

        $applicationArchivePath = $temporaryDirectory.'/'.$applicationArchiveName;
        $installerPath = $temporaryDirectory.'/'.$installerName;
        $packagePath = $disk->path($outputDirectory.'/'.$packageName);

        try {
            if ($this->files->exists($packagePath)) {
                $this->files->delete($packagePath);
            }

            $manifest = ['format' => 2, 'components' => array_values(array_unique($components)), 'files' => []];
            if (in_array('base', $components, true)) {
                $this->runBuildCommands();
                $this->createApplicationArchive($applicationArchivePath);
                $manifest['files'][$applicationArchiveName] = hash_file('sha256', $applicationArchivePath);
            }
            if (in_array('database', $components, true)) {
                (new DatabaseSnapshot)->export(DB::connection()->getPdo(), $temporaryDirectory.'/database.enc', (string) $databasePassword, (string) config('app.key'));
                $manifest['files']['database.enc'] = hash_file('sha256', $temporaryDirectory.'/database.enc');
            }
            if (in_array('media', $components, true)) {
                $manifest['media'] = $this->createMediaArchive($temporaryDirectory.'/media.zip');
                $manifest['files']['media.zip'] = hash_file('sha256', $temporaryDirectory.'/media.zip');
            }
            if (in_array('plugins', $components, true)) {
                $plugins = InstalledPlugin::query()->whereNotNull('installed_version')->get([
                    'slug', 'name', 'package', 'label', 'description', 'provider', 'installed_version', 'status',
                ])->toArray();
                $this->files->put($temporaryDirectory.'/plugins.json', json_encode($plugins, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $manifest['files']['plugins.json'] = hash_file('sha256', $temporaryDirectory.'/plugins.json');
            }
            $runtime = $temporaryDirectory.'/deployment-runtime.php';
            $this->files->copy(app_path('Services/Deployment/DatabaseSnapshot.php'), $runtime);
            $manifest['files']['deployment-runtime.php'] = hash_file('sha256', $runtime);
            $this->files->put($temporaryDirectory.'/deployment.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->files->put($installerPath, $this->renderInstaller($applicationArchiveName, $manifest));
            $package = $this->openArchive($packagePath);
            foreach (array_keys($manifest['files']) as $filename) {
                if (! $package->addFile($temporaryDirectory.'/'.$filename, $filename)) {
                    throw new RuntimeException('Não foi possível adicionar um componente ao pacote.');
                }
            }
            $package->addFile($temporaryDirectory.'/deployment.json', 'deployment.json');
            $package->addFile($installerPath, $installerName);
            if (! $package->close()) {
                throw new RuntimeException('Não foi possível finalizar o pacote de instalação.');
            }
            chmod($packagePath, 0600);

            return [
                'path' => $packagePath,
                'relative_path' => $outputDirectory.'/'.$packageName,
                'size' => $this->files->size($packagePath),
                'created_at' => now()->toDateTimeString(),
                'warnings' => $this->warnings,
            ];
        } catch (Throwable $exception) {
            $this->files->delete($packagePath);
            throw $exception;
        } finally {
            $this->files->deleteDirectory($temporaryDirectory);
        }
    }

    private function createMediaArchive(string $path): array
    {
        $diskName = (string) config('cms-core.media.disk', 'public');
        if (config('filesystems.disks.'.$diskName.'.driver') !== 'local') {
            throw new RuntimeException('A exportação de media exige um disco local.');
        }
        $disk = Storage::disk($diskName);
        $directory = trim((string) config('cms-core.media.directory', 'cms/media'), '/');
        $root = str_replace('\\', '/', $disk->path(''));
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        if (! str_starts_with($root, $base) || $directory === '' || str_contains($directory, '..')) {
            throw new RuntimeException('A pasta de media tem de pertencer ao projeto.');
        }
        $relativeRoot = trim(substr($root, strlen($base)), '/');
        if (! str_starts_with($relativeRoot.'/', 'storage/app/') && ! str_starts_with($relativeRoot.'/', 'public/')) {
            throw new RuntimeException('O disco de media tem de estar em storage/app ou public.');
        }
        $archive = $this->openArchive($path);
        $archive->addEmptyDir($directory);
        foreach ($disk->allFiles($directory) as $filename) {
            $source = $disk->path($filename);
            if (is_link($source) || ! is_readable($source) || ! $archive->addFile($source, $filename)) {
                throw new RuntimeException('Não foi possível adicionar um ficheiro de media.');
            }
        }
        if (! $archive->close()) {
            throw new RuntimeException('Não foi possível finalizar o pacote de media.');
        }

        return ['disk' => $diskName, 'root' => $relativeRoot, 'directory' => $directory];
    }

    private function runBuildCommands(): void
    {
        $commands = (array) config('deploy.build_commands', []);
        $timeout = (int) config('deploy.build_timeout', 300);
        $attempts = max(1, (int) config('deploy.build_attempts', 3));

        foreach ($commands as $command) {
            $command = trim((string) $command);

            if ($command === '') {
                continue;
            }

            $process = null;

            for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                $process = $this->createBuildProcess($command);
                $process->setTimeout($timeout > 0 ? $timeout : null);
                $process->run();

                if ($process->isSuccessful()) {
                    break;
                }
            }

            if ($process === null || ! $process->isSuccessful()) {
                if ($this->useBuildFallback($command)) {
                    continue;
                }

                throw new RuntimeException(sprintf(
                    'O comando de compilação "%s" falhou: %s',
                    $command,
                    trim($process->getErrorOutput() ?: $process->getOutput()),
                ));
            }
        }
    }

    private function useBuildFallback(string $command): bool
    {
        $fallbacks = (array) config('deploy.build_fallbacks', []);
        $fallback = $fallbacks[$command] ?? null;

        if (! is_string($fallback) || $fallback === '' || ! $this->files->isFile(base_path($fallback))) {
            return false;
        }

        if ($this->buildFallbackIsFresh($fallback)) {
            return true;
        }

        $this->warnings[] = sprintf(
            'O comando "%s" falhou. Foram utilizados os assets previamente compilados em "%s".',
            $command,
            str_replace('\\', '/', $fallback),
        );

        return true;
    }

    private function buildFallbackIsFresh(string $fallback): bool
    {
        $manifestModifiedAt = $this->files->lastModified(base_path($fallback));

        foreach ((array) config('deploy.build_sources', []) as $source) {
            $path = base_path((string) $source);

            if ($this->files->isFile($path) && $this->files->lastModified($path) > $manifestModifiedAt) {
                return false;
            }

            if (! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($file->getMTime() > $manifestModifiedAt) {
                    return false;
                }
            }
        }

        return true;
    }

    private function createBuildProcess(string $command): Process
    {
        if (preg_match('/^npm(?=\s|$)/', $command) === 1) {
            $npm = $this->findNpmExecutable();

            if ($npm === null) {
                throw new RuntimeException(
                    'O npm não foi encontrado. Instale o Node.js ou defina o npm no PATH antes de compilar.',
                );
            }

            return new Process([$npm, ...$this->commandArguments($command, 'npm')], base_path());
        }

        if (preg_match('/^php(?=\s|$)/', $command) === 1) {
            return new Process([PHP_BINARY, ...$this->commandArguments($command, 'php')], base_path());
        }

        return Process::fromShellCommandline($command, base_path());
    }

    /** @return array<int, string> */
    private function commandArguments(string $command, string $executable): array
    {
        $arguments = trim(substr($command, strlen($executable)));

        if ($arguments === '') {
            return [];
        }

        return preg_split('/\s+/', $arguments) ?: [];
    }

    private function findNpmExecutable(): ?string
    {
        $executable = (new ExecutableFinder)->find(PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm');

        if ($executable !== null) {
            return $executable;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return null;
        }

        $candidates = array_filter([
            getenv('ProgramFiles') ? getenv('ProgramFiles').'\\nodejs\\npm.cmd' : null,
            getenv('ProgramFiles(x86)') ? getenv('ProgramFiles(x86)').'\\nodejs\\npm.cmd' : null,
            getenv('APPDATA') ? getenv('APPDATA').'\\npm\\npm.cmd' : null,
        ]);

        foreach ($candidates as $candidate) {
            if ($this->files->isFile($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function createApplicationArchive(string $archivePath): void
    {
        $archive = $this->openArchive($archivePath);
        $basePath = base_path();
        $exclude = $this->normaliseExclusions((array) config('deploy.exclude', []));
        $exclude = array_merge($exclude, ['storage', 'output', 'auth.json', 'public/.cms_installed', 'public/installer.php', 'public/application.zip']);
        $pathRepositoryCopies = $this->pathRepositoryCopies();
        $exclude = array_merge($exclude, array_column($pathRepositoryCopies, 'destination'));

        $finder = (new Finder)->files()->ignoreDotFiles(false)->ignoreVCS(false)->in($basePath)->exclude($exclude);
        foreach ($finder as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            if ($this->shouldExclude($relativePath, $exclude)
                || (preg_match('#(^|/)(\.env(?:\..*)?|auth\.json|\.git)(/|$)#', $relativePath) && basename($relativePath) !== '.env.example')
                || preg_match('#^database/.*\.(sqlite|sqlite3|db)(-wal|-shm)?$#i', $relativePath)) {
                continue;
            }

            if ($file->isLink() || ! $this->files->isReadable($file->getPathname())) {
                throw new RuntimeException('O ficheiro "'.$relativePath.'" não pode ser lido para o pacote de deploy.');
            }

            if (! $archive->addFile($file->getPathname(), $relativePath)) {
                throw new RuntimeException('Não foi possível adicionar o ficheiro "'.$relativePath.'" ao pacote de deploy.');
            }
        }

        foreach ($pathRepositoryCopies as $copy) {
            $this->addDirectoryToArchive($archive, $copy['source'], $copy['destination']);
        }

        $composerPhar = $this->composerPharPath();

        if ($composerPhar === null) {
            throw new RuntimeException(
                'Não foi possível localizar um composer.phar válido para incluir no pacote de deploy.',
            );
        }

        if ($archive->locateName('composer.phar') === false && ! $archive->addFile($composerPhar, 'composer.phar')) {
            throw new RuntimeException('Não foi possível adicionar o composer.phar ao pacote de deploy.');
        }

        if (! $archive->close()) {
            throw new RuntimeException('Não foi possível finalizar o ficheiro ZIP da aplicação.');
        }
    }

    private function composerPharPath(): ?string
    {
        $configured = trim((string) config('deploy.composer_phar', ''));
        $composer = (new ExecutableFinder)->find(PHP_OS_FAMILY === 'Windows' ? 'composer.bat' : 'composer');
        $candidates = array_filter([
            $configured !== '' ? $configured : null,
            base_path('composer.phar'),
            $composer !== null ? dirname($composer).DIRECTORY_SEPARATOR.'composer.phar' : null,
            $composer,
        ]);

        foreach (array_unique($candidates) as $candidate) {
            $path = realpath($candidate);

            if ($path === false || ! $this->files->isFile($path) || ! $this->files->isReadable($path)) {
                continue;
            }

            try {
                new Phar($path);

                return $path;
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{source:string,destination:string}>
     */
    private function pathRepositoryCopies(): array
    {
        $lockPath = base_path('composer.lock');

        if (! $this->files->exists($lockPath)) {
            return [];
        }

        $lock = json_decode($this->files->get($lockPath), true);
        $packages = array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []);
        $copies = [];

        foreach ($packages as $package) {
            if (($package['dist']['type'] ?? null) !== 'path') {
                continue;
            }

            $name = (string) ($package['name'] ?? '');
            $source = (string) ($package['dist']['url'] ?? '');

            if ($name === '' || $source === '') {
                continue;
            }

            if (! str_starts_with($source, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $source)) {
                $source = base_path($source);
            }

            $source = realpath($source);

            if ($source === false || ! $this->files->isDirectory($source)) {
                throw new RuntimeException('O path repository "'.$name.'" não foi encontrado para o pacote de deploy.');
            }

            $copies[] = ['source' => $source, 'destination' => 'vendor/'.$name];
            $copies[] = ['source' => $source, 'destination' => 'packages/'.$name];
        }

        foreach ((array) config('deploy.local_package_overlays', []) as $name => $source) {
            $source = (string) $source;

            if (! str_starts_with($source, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $source)) {
                $source = base_path($source);
            }

            $source = realpath($source);

            if ($source === false || ! $this->files->isDirectory($source)) {
                throw new RuntimeException('O pacote local "'.$name.'" não foi encontrado para o pacote de deploy.');
            }

            $copies[] = [
                'source' => $source,
                'destination' => 'vendor/'.trim((string) $name, '/'),
            ];
        }

        return array_values(array_column($copies, null, 'destination'));
    }

    private function addDirectoryToArchive(ZipArchive $archive, string $source, string $destination): void
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $destination = trim(str_replace('\\', '/', $destination), '/');

        $finder = (new Finder)->files()->ignoreDotFiles(false)->in($source)->exclude(['.git', 'node_modules', 'vendor']);
        foreach ($finder as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            if ($this->shouldExclude($relativePath, ['.git', 'node_modules', 'vendor'])
                || (preg_match('#(^|/)(\.env(?:\..*)?|auth\.json)(/|$)#', $relativePath) && basename($relativePath) !== '.env.example')) {
                continue;
            }

            $archivePath = $destination.'/'.$relativePath;

            if ($file->isLink() || ! $this->files->isReadable($file->getPathname())) {
                throw new RuntimeException('O ficheiro "'.$archivePath.'" não pode ser lido para o pacote de deploy.');
            }

            if (! $archive->addFile($file->getPathname(), $archivePath)) {
                throw new RuntimeException('Não foi possível adicionar o ficheiro "'.$archivePath.'" ao pacote de deploy.');
            }
        }
    }

    /**
     * @param  array<int, string>  $exclude
     * @return array<int, string>
     */
    private function normaliseExclusions(array $exclude): array
    {
        return array_values(array_filter(array_map(
            fn ($path) => trim(str_replace('\\', '/', (string) $path), '/'),
            $exclude,
        )));
    }

    /**
     * @param  array<int, string>  $exclude
     */
    private function shouldExclude(string $relativePath, array $exclude): bool
    {
        $relativePath = trim($relativePath, '/');

        return Arr::first($exclude, function (string $excludedPath) use ($relativePath): bool {
            return $relativePath === $excludedPath || str_starts_with($relativePath, $excludedPath.'/');
        }) !== null;
    }

    private function openArchive(string $path): ZipArchive
    {
        $archive = new ZipArchive;

        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o ficheiro ZIP de instalação.');
        }

        return $archive;
    }

    private function renderInstaller(string $applicationArchiveName, array $manifest = []): string
    {
        $stub = $this->files->get(resource_path('installer/installer.php.stub'));

        return str_replace(
            ['{{ application_archive_name }}', '{{ deployment_manifest }}'],
            [$applicationArchiveName, base64_encode(json_encode($manifest, JSON_THROW_ON_ERROR))],
            $stub,
        );
    }
}
