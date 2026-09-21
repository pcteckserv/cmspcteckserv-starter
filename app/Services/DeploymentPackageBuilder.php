<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

class DeploymentPackageBuilder
{
    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct(
        private readonly Filesystem $files,
    ) {
    }

    /**
     * @return array{path:string,relative_path:string,size:int,created_at:string,warnings:array<int, string>}
     */
    public function build(): array
    {
        $this->warnings = [];

        if (! extension_loaded('zip')) {
            throw new RuntimeException('A extensão PHP ZipArchive não está disponível.');
        }

        $disk = Storage::disk((string) config('deploy.output_disk', 'local'));
        $outputDirectory = trim((string) config('deploy.output_directory', 'deploy'), '/');
        $packageName = (string) config('deploy.package_name', 'cmspcteckserv-deploy.zip');
        $applicationArchiveName = (string) config('deploy.application_archive_name', 'application.zip');
        $installerName = (string) config('deploy.installer_name', 'installer.php');

        $disk->makeDirectory($outputDirectory);

        $temporaryDirectory = storage_path('app/'.$outputDirectory.'/tmp-'.date('YmdHis').'-'.bin2hex(random_bytes(4)));
        $this->files->ensureDirectoryExists($temporaryDirectory);

        $applicationArchivePath = $temporaryDirectory.'/'.$applicationArchiveName;
        $installerPath = $temporaryDirectory.'/'.$installerName;
        $packagePath = $disk->path($outputDirectory.'/'.$packageName);

        try {
            if ($this->files->exists($packagePath)) {
                $this->files->delete($packagePath);
            }

            $this->runBuildCommands();
            $this->createApplicationArchive($applicationArchivePath);
            $this->files->put($installerPath, $this->renderInstaller($applicationArchiveName));

            $package = $this->openArchive($packagePath);
            $package->addFile($applicationArchivePath, $applicationArchiveName);
            $package->addFile($installerPath, $installerName);
            $package->close();

            return [
                'path' => $packagePath,
                'relative_path' => $outputDirectory.'/'.$packageName,
                'size' => $this->files->size($packagePath),
                'created_at' => now()->toDateTimeString(),
                'warnings' => $this->warnings,
            ];
        } finally {
            $this->files->deleteDirectory($temporaryDirectory);
        }
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
        $executable = (new ExecutableFinder())->find(PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm');

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
        $pathRepositoryCopies = $this->pathRepositoryCopies();
        $exclude = array_merge($exclude, array_column($pathRepositoryCopies, 'destination'));

        foreach ($this->files->allFiles($basePath, true) as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            if ($this->shouldExclude($relativePath, $exclude)) {
                continue;
            }

            if (! $this->files->isReadable($file->getPathname())) {
                throw new RuntimeException('O ficheiro "'.$relativePath.'" não pode ser lido para o pacote de deploy.');
            }

            if (! $archive->addFile($file->getPathname(), $relativePath)) {
                throw new RuntimeException('Não foi possível adicionar o ficheiro "'.$relativePath.'" ao pacote de deploy.');
            }
        }

        foreach ($pathRepositoryCopies as $copy) {
            $this->addDirectoryToArchive($archive, $copy['source'], $copy['destination']);
        }

        if (! $archive->close()) {
            throw new RuntimeException('Não foi possível finalizar o ficheiro ZIP da aplicação.');
        }
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

            $copies[] = [
                'source' => $source,
                'destination' => 'vendor/'.$name,
            ];
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

        foreach ($this->files->allFiles($source, true) as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            if ($this->shouldExclude($relativePath, ['.git', 'node_modules', 'vendor'])) {
                continue;
            }

            $archivePath = $destination.'/'.$relativePath;

            if (! $this->files->isReadable($file->getPathname())) {
                throw new RuntimeException('O ficheiro "'.$archivePath.'" não pode ser lido para o pacote de deploy.');
            }

            if (! $archive->addFile($file->getPathname(), $archivePath)) {
                throw new RuntimeException('Não foi possível adicionar o ficheiro "'.$archivePath.'" ao pacote de deploy.');
            }
        }
    }

    /**
     * @param array<int, string> $exclude
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
     * @param array<int, string> $exclude
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
        $archive = new ZipArchive();

        if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o ficheiro ZIP de instalação.');
        }

        return $archive;
    }

    private function renderInstaller(string $applicationArchiveName): string
    {
        $stub = $this->files->get(resource_path('installer/installer.php.stub'));

        return str_replace('{{ application_archive_name }}', $applicationArchiveName, $stub);
    }
}
