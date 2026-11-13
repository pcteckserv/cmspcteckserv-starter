<?php

namespace Tests\Unit;

use App\Services\DeploymentPackageBuilder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class DeploymentRepairTest extends TestCase
{
    private string $directory;

    private string $passwordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/cms-repair-test-'.bin2hex(random_bytes(8));
        $this->passwordHash = password_hash(hash('sha256', 'palavra-passe-de-teste'), PASSWORD_DEFAULT);
        foreach (['public', 'app/vendor/composer', 'app/storage/app/public', 'app/database', 'sessions'] as $path) {
            mkdir($this->directory.'/'.$path, 0755, true);
        }
        file_put_contents($this->directory.'/app/.env', 'APP_KEY=chave-apenas-de-teste'."\nCMS_GITHUB_TOKEN=token-apenas-de-teste\nCMS_PHP_CLI_BINARY=".PHP_BINARY."\n");
        file_put_contents($this->directory.'/app/composer.json', '{"require":{"pcteckserv/cms-core":"^3.1.1"}}');
        file_put_contents($this->directory.'/app/composer.lock', '{"content-hash":"00000000000000000000000000000000","packages":[{"name":"pcteckserv/cms-core","version":"v3.1.1"}],"packages-dev":[]}');
        file_put_contents($this->directory.'/app/vendor/composer/installed.json', '{"packages":[{"name":"pcteckserv/cms-core","version":"v3.0.1"}]}');
        file_put_contents($this->directory.'/app/storage/app/public/upload.txt', 'upload preservado');
        file_put_contents($this->directory.'/app/database/database.sqlite', 'base de dados preservada');
        file_put_contents($this->directory.'/app/artisan', '<?php file_put_contents("artisan-commands.json", json_encode($argv)); echo "Artisan executado";');
        $this->renderRepair();
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            if (! $item->isLink()) {
                chmod($item->getPathname(), $item->isDir() ? 0755 : 0644);
            }
        }
        $this->assertTrue((new Filesystem)->deleteDirectory($this->directory));
        parent::tearDown();
    }

    private function renderRepair(?int $expiry = null, ?string $composer = null): void
    {
        $composer ??= '<?php file_put_contents("composer-commands.json", json_encode($argv)); echo "Composer executado";';
        $source = str_replace(
            ['{{ password_hash }}', '{{ expires_at }}', '{{ composer_payload }}', '{{ composer_checksum }}'],
            [var_export($this->passwordHash, true), (string) ($expiry ?? time() + 3600), base64_encode($composer), hash('sha256', $composer)],
            file_get_contents(resource_path('installer/repair.php.stub')),
        );
        file_put_contents($this->directory.'/public/repair.php', $source);
    }

    private function request(array $input = [], bool $validCsrf = true, string $method = 'POST'): string
    {
        $wrapper = '<?php session_save_path($argv[1]."/sessions"); session_start(); '
            .'$_SERVER["REQUEST_METHOD"]=$argv[2]; $_POST=json_decode(base64_decode($argv[3]),true); '
            .'$hash=json_decode(base64_decode($argv[4]),true); '
            .'$_SESSION["cms_repair_csrf_".hash("sha256",realpath($argv[1]."/public/repair.php").$hash)]="csrf-de-teste"; '
            .'require $argv[1]."/public/repair.php";';
        file_put_contents($this->directory.'/request.php', $wrapper);
        $input = array_merge(['action' => 'diagnose', 'password' => 'palavra-passe-de-teste', 'csrf_token' => $validCsrf ? 'csrf-de-teste' : 'invalido'], $input);
        $process = new Process([PHP_BINARY, $this->directory.'/request.php', $this->directory, $method, base64_encode(json_encode($input)), base64_encode(json_encode($this->passwordHash))]);
        $process->mustRun();

        return $process->getOutput();
    }

    public function test_diagnostica_divergencia_sem_alterar_dados_e_sem_exigir_laravel(): void
    {
        file_put_contents($this->directory.'/public/.cms_installed', 'instalação já efetuada');
        $before = $this->preservedFiles();
        $html = $this->request();
        $this->assertStringContainsString('lock: v3.1.1; instalada: v3.0.1', $html);
        $this->assertStringNotContainsString('chave-apenas-de-teste', $html);
        $this->assertSame($before, $this->preservedFiles());
        $this->assertSame('instalação já efetuada', file_get_contents($this->directory.'/public/.cms_installed'));
        $this->assertFileDoesNotExist($this->directory.'/app/composer-commands.json');
    }

    public function test_bloqueia_acesso_anonimo_password_invalida_csrf_e_expiracao(): void
    {
        $this->assertStringNotContainsString('lock: v3.1.1', $this->request([], true, 'GET'));
        $this->assertStringContainsString('Palavra-passe inválida', $this->request(['password' => 'errada']));
        $this->assertStringContainsString('O formulário expirou', $this->request([], false));
        $this->renderRepair(time() - 1);
        $this->assertStringContainsString('O acesso de reparação expirou', $this->request());
        $this->assertFileDoesNotExist($this->directory.'/app/composer-commands.json');
    }

    public function test_limita_tentativas_entre_sessoes_distintas(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->assertStringContainsString('Palavra-passe inválida', $this->request(['password' => 'errada']));
        }
        $this->assertStringContainsString('Demasiadas tentativas', $this->request());
    }

    public function test_repara_lock_sem_scripts_plugins_migrations_ou_alteracao_de_dados(): void
    {
        $before = $this->preservedFiles();
        $this->assertStringContainsString('Confirme que tem uma cópia', $this->request(['action' => 'install']));
        $this->assertFileDoesNotExist($this->directory.'/app/composer-commands.json');
        $html = $this->request(['action' => 'install', 'backup_confirmed' => '1']);
        $this->assertStringContainsString('Operação concluída', $html);
        $arguments = json_decode(file_get_contents($this->directory.'/app/composer-commands.json'), true);
        $this->assertContains('install', $arguments);
        $this->assertContains('--no-scripts', $arguments);
        $this->assertContains('--no-plugins', $arguments);
        $this->assertNotContains('update', $arguments);
        $this->assertNotContains('require', $arguments);
        $this->assertFileDoesNotExist($this->directory.'/app/artisan-commands.json');
        $this->assertSame($before, $this->preservedFiles());
    }

    public function test_mostra_final_do_erro_sanitizado_e_escapado(): void
    {
        $this->renderRepair(composer: '<?php echo str_repeat("progresso ", 200)." FALHA FINAL <script>alert(1)</script> token-apenas-de-teste chave-apenas-de-teste https://utilizador:segredo@example.test/repo"; exit(1);');
        $html = $this->request(['action' => 'install', 'backup_confirmed' => '1']);
        $this->assertStringContainsString('FALHA FINAL', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('token-apenas-de-teste', $html);
        $this->assertStringNotContainsString('chave-apenas-de-teste', $html);
        $this->assertStringNotContainsString('utilizador:segredo', $html);
    }

    public function test_migrations_exigem_confirmacao_e_payloads_arbitrarios_sao_recusados(): void
    {
        $this->assertStringContainsString('Confirme a aplicação', $this->request(['action' => 'migrate']));
        $this->assertStringContainsString('Operação de reparação inválida', $this->request(['action' => 'migrate:fresh']));
        $this->assertFileDoesNotExist($this->directory.'/app/artisan-commands.json');
        $this->assertStringContainsString('Operação concluída', $this->request(['action' => 'migrate', 'migrations_confirmed' => '1']));
        $arguments = json_decode(file_get_contents($this->directory.'/app/artisan-commands.json'), true);
        $this->assertSame(['migrate', '--force', '--no-interaction', '--no-ansi'], array_slice($arguments, 1));
    }

    public function test_desativacao_persistente_nao_depende_da_sessao(): void
    {
        $this->assertStringContainsString('Reparador desativado', $this->request(['action' => 'finish']));
        $this->assertStringContainsString('Este reparador foi desativado', $this->request());
        $this->assertFileDoesNotExist($this->directory.'/app/composer-commands.json');
    }

    public function test_gera_pacote_autonomo_sem_exportar_ficheiros_ou_segredos_do_site(): void
    {
        Storage::fake('local');
        config()->set('deploy.local_package_overlays', ['package/inexistente' => '/nao-existe']);
        $package = (new DeploymentPackageBuilder(new Filesystem))->build(['repair'], 'abc');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($package['path']));
        $this->assertSame(2, $zip->numFiles);
        $source = $zip->getFromIndex(0);
        $this->assertMatchesRegularExpression('/^repair-[a-f0-9]{16}\.php$/', $zip->getNameIndex(0));
        $this->assertStringNotContainsString('{{ password_hash }}', $source);
        $this->assertStringNotContainsString('palavra-passe-de-teste', $source);
        $this->assertStringContainsString('CmsRepairTool', $source);
        preg_match("/new CmsRepairTool\('([^']+)'/", $source, $matches);
        $this->assertTrue(password_verify(hash('sha256', 'abc'), $matches[1]));
        $zip->close();
    }

    public function test_executa_o_composer_real_incluido_sem_depender_do_composer_do_alojamento(): void
    {
        $builder = new DeploymentPackageBuilder(new Filesystem);
        $composerPath = (new \ReflectionClass($builder))->getMethod('composerPharPath')->invoke($builder);
        $this->assertNotNull($composerPath);
        $this->renderRepair(composer: file_get_contents($composerPath));

        $html = $this->request(['action' => 'autoload']);
        $this->assertStringContainsString('Operação concluída', $html);
        $this->assertFileExists($this->directory.'/app/vendor/autoload.php');
        $this->assertFileDoesNotExist($this->directory.'/app/artisan-commands.json');
    }

    public function test_limpa_manifests_de_arranque_sem_apagar_outros_ficheiros_ou_dados(): void
    {
        mkdir($this->directory.'/app/bootstrap/cache', 0755, true);
        file_put_contents($this->directory.'/app/bootstrap/cache/packages.php', 'manifest antigo');
        file_put_contents($this->directory.'/app/bootstrap/cache/.gitignore', '*');
        $before = $this->preservedFiles();
        $this->assertStringContainsString('Operação concluída', $this->request(['action' => 'caches']));
        $this->assertFileDoesNotExist($this->directory.'/app/bootstrap/cache/packages.php');
        $this->assertFileExists($this->directory.'/app/bootstrap/cache/.gitignore');
        $this->assertSame($before, $this->preservedFiles());
        $this->assertStringNotContainsString('cache:clear', file_get_contents($this->directory.'/app/artisan-commands.json'));
    }

    public function test_impede_reparacoes_simultaneas_na_mesma_aplicacao(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'cms-repair-'.hash('sha256', 'application-'.realpath($this->directory.'/app')).'.lock';
        $handle = fopen($path, 'c+');
        flock($handle, LOCK_EX);
        try {
            $html = $this->request(['action' => 'install', 'backup_confirmed' => '1']);
            $this->assertStringContainsString('Já existe uma operação em execução', $html);
            $this->assertFileDoesNotExist($this->directory.'/app/composer-commands.json');
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function coreWithoutGit(): void
    {
        mkdir($this->directory.'/app/vendor/pcteckserv/cms-core', 0755, true);
        file_put_contents($this->directory.'/app/vendor/pcteckserv/cms-core/preserved.txt', 'código anterior');
        $repository = $this->directory.'/git-source';
        mkdir($repository);
        file_put_contents($repository.'/preserved.txt', 'código anterior');
        file_put_contents($repository.'/composer.json', '{"name":"pcteckserv/cms-core","type":"library"}');
        foreach ([['git', 'init'], ['git', 'add', '.'], ['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'Versão de teste']] as $command) {
            (new Process($command, $repository))->mustRun();
        }
        $reference = new Process(['git', 'rev-parse', 'HEAD'], $repository);
        $reference->mustRun();
        file_put_contents($this->directory.'/app/vendor/composer/installed.json', json_encode(['packages' => [[
            'name' => 'pcteckserv/cms-core', 'version' => 'v3.0.1', 'installation-source' => 'source',
            'source' => ['type' => 'git', 'url' => $repository, 'reference' => trim($reference->getOutput())],
        ]]]));
        file_put_contents($this->directory.'/app/composer.lock', json_encode(['packages' => [[
            'name' => 'pcteckserv/cms-core', 'version' => 'v3.1.1',
            'source' => ['type' => 'git', 'url' => $repository, 'reference' => trim($reference->getOutput())],
        ]]]));
        $this->renderRepair(composer: '<?php file_put_contents("composer-commands.json", json_encode($argv)); if (!in_array("--dry-run", $argv, true)) { $lock=json_decode(file_get_contents("composer.lock"),true); file_put_contents("vendor/composer/installed.json",json_encode(["packages"=>$lock["packages"]])); }');
    }

    public function test_recuperacao_git_exige_confirmacao_e_preserva_backup_privado(): void
    {
        $this->coreWithoutGit();
        $before = $this->preservedFiles();
        $this->assertStringContainsString('Confirme que tem uma cópia', $this->request(['action' => 'core-git']));
        $this->assertFileDoesNotExist($this->directory.'/app/composer-commands.json');
        $html = $this->request(['action' => 'core-git', 'backup_confirmed' => '1']);
        $this->assertStringContainsString('Metadados Git recuperados', $html);
        $this->assertDirectoryExists($this->directory.'/app/vendor/pcteckserv/cms-core/.git');
        $arguments = json_decode(file_get_contents($this->directory.'/app/composer-commands.json'), true);
        $this->assertContains('install', $arguments);
        $this->assertContains('--no-scripts', $arguments);
        $this->assertContains('--no-plugins', $arguments);
        $backups = glob($this->directory.'/app/storage/app/private/cms-repair-backups/core-*');
        $this->assertCount(1, $backups);
        $this->assertSame('código anterior', file_get_contents($backups[0].'/core/preserved.txt'));
        $this->assertFileExists($backups[0].'/composer/installed.json');
        $this->assertSame($before, $this->preservedFiles());
        $this->assertFileDoesNotExist($this->directory.'/app/artisan-commands.json');
    }

    public function test_falha_na_recuperacao_preserva_core_e_metadados_e_guarda_a_tentativa(): void
    {
        $this->coreWithoutGit();
        $installed = json_decode(file_get_contents($this->directory.'/app/vendor/composer/installed.json'), true);
        $metadata = json_encode($installed);
        file_put_contents($this->directory.'/app/vendor/composer/installed.json', $metadata);
        $lock = json_decode(file_get_contents($this->directory.'/app/composer.lock'), true);
        $lock['packages'][0]['source']['reference'] = str_repeat('b', 40);
        file_put_contents($this->directory.'/app/composer.lock', json_encode($lock));
        $html = $this->request(['action' => 'core-git', 'backup_confirmed' => '1']);
        $this->assertStringContainsString('A pasta do Core e os registos do Composer não foram substituídos', $html);
        $this->assertSame('código anterior', file_get_contents($this->directory.'/app/vendor/pcteckserv/cms-core/preserved.txt'));
        $this->assertSame($metadata, file_get_contents($this->directory.'/app/vendor/composer/installed.json'));
        $backups = glob($this->directory.'/app/storage/app/private/cms-repair-backups/core-*');
        $this->assertDirectoryExists($backups[0].'/staged-core/.git');
        $this->assertSame('código anterior', file_get_contents($backups[0].'/core/preserved.txt'));
    }

    public function test_recusa_reinstalar_core_que_ja_tem_git_ou_nao_e_source_git(): void
    {
        $html = $this->request(['action' => 'core-git', 'backup_confirmed' => '1']);
        $this->assertStringContainsString('exige um Core registado como instalação Git/source', $html);
        $this->coreWithoutGit();
        mkdir($this->directory.'/app/vendor/pcteckserv/cms-core/.git');
        $this->assertStringContainsString('sem metadados .git', $this->request(['action' => 'core-git', 'backup_confirmed' => '1']));
        $this->assertFileDoesNotExist($this->directory.'/app/composer-commands.json');
    }

    public function test_recusa_origem_com_credenciais_ou_referencia_git_manipulada(): void
    {
        $this->coreWithoutGit();
        $path = $this->directory.'/app/vendor/composer/installed.json';
        $metadata = json_decode(file_get_contents($path), true);
        $metadata['packages'][0]['source']['url'] = 'https://credencial-de-teste@example.test/core.git';
        file_put_contents($path, json_encode($metadata));
        $html = $this->request(['action' => 'core-git', 'backup_confirmed' => '1']);
        $this->assertStringContainsString('exige um Core registado como instalação Git/source', $html);
        $this->assertStringNotContainsString('credencial-de-teste', $html);
        $metadata['packages'][0]['source']['url'] = $this->directory.'/git-source';
        $metadata['packages'][0]['source']['reference'] = '--force';
        file_put_contents($path, json_encode($metadata));
        $this->assertStringContainsString('exige um Core registado como instalação Git/source', $this->request(['action' => 'core-git', 'backup_confirmed' => '1']));
        $this->assertFileDoesNotExist($this->directory.'/app/composer-commands.json');
    }

    public function test_falha_de_instalacao_apos_troca_nao_repoe_metadados_antigos(): void
    {
        $this->coreWithoutGit();
        file_put_contents($this->directory.'/git-source/preserved.txt', 'código recuperado');
        foreach ([['git', 'add', '.'], ['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'Versão do lock']] as $command) {
            (new Process($command, $this->directory.'/git-source'))->mustRun();
        }
        $reference = new Process(['git', 'rev-parse', 'HEAD'], $this->directory.'/git-source');
        $reference->mustRun();
        $lock = json_decode(file_get_contents($this->directory.'/app/composer.lock'), true);
        $lock['packages'][0]['source']['reference'] = trim($reference->getOutput());
        file_put_contents($this->directory.'/app/composer.lock', json_encode($lock));
        $this->renderRepair(composer: '<?php if (!in_array("--dry-run",$argv,true)) { file_put_contents("vendor/composer/installed.json", "{\"packages\":[],\"partial\":true}"); exit(1); }');
        $html = $this->request(['action' => 'core-git', 'backup_confirmed' => '1']);
        $this->assertStringContainsString('A instalação do lock não terminou', $html);
        $this->assertSame('código recuperado', file_get_contents($this->directory.'/app/vendor/pcteckserv/cms-core/preserved.txt'));
        $this->assertTrue(json_decode(file_get_contents($this->directory.'/app/vendor/composer/installed.json'), true)['partial']);
        $backups = glob($this->directory.'/app/storage/app/private/cms-repair-backups/core-*');
        $this->assertSame('código anterior', file_get_contents($backups[0].'/original-core/preserved.txt'));
    }

    public function test_composer_real_recupera_git_em_falta_e_atualiza_para_o_lock_sem_perder_dados(): void
    {
        $builder = new DeploymentPackageBuilder(new Filesystem);
        $composerPath = (new \ReflectionClass($builder))->getMethod('composerPharPath')->invoke($builder);
        $repository = $this->directory.'/repository';
        mkdir($repository);
        $run = function (array $arguments, string $cwd) {
            $process = new Process($arguments, $cwd, ['GIT_CONFIG_NOSYSTEM' => '1']);
            $process->setTimeout(60);
            $process->mustRun();

            return trim($process->getOutput());
        };
        $run(['git', 'init'], $repository);
        file_put_contents($repository.'/composer.json', '{"name":"pcteckserv/cms-core","type":"library"}');
        file_put_contents($repository.'/version.txt', '1.0.0');
        $run(['git', 'add', '.'], $repository);
        $run(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'Primeira versão'], $repository);
        $oldReference = $run(['git', 'rev-parse', 'HEAD'], $repository);
        file_put_contents($repository.'/version.txt', '1.1.0');
        $run(['git', 'add', '.'], $repository);
        $run(['git', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-m', 'Segunda versão'], $repository);
        $newReference = $run(['git', 'rev-parse', 'HEAD'], $repository);
        $packages = [];
        foreach (['1.0.0' => $oldReference, '1.1.0' => $newReference] as $version => $reference) {
            $packages[] = ['name' => 'pcteckserv/cms-core', 'version' => $version, 'type' => 'library', 'source' => ['type' => 'git', 'url' => $repository, 'reference' => $reference]];
        }
        $manifest = ['name' => 'fixture/repair', 'require' => ['pcteckserv/cms-core' => '1.0.0'], 'repositories' => [['type' => 'package', 'package' => $packages], ['packagist.org' => false]]];
        $appPath = $this->directory.'/app';
        unlink($appPath.'/composer.lock');
        unlink($appPath.'/vendor/composer/installed.json');
        file_put_contents($appPath.'/composer.json', json_encode($manifest));
        $run([PHP_BINARY, $composerPath, 'update', '--no-scripts', '--no-plugins', '--no-interaction', '--prefer-source'], $appPath);
        $manifest['require']['pcteckserv/cms-core'] = '1.1.0';
        file_put_contents($appPath.'/composer.json', json_encode($manifest));
        $run([PHP_BINARY, $composerPath, 'update', '--no-install', '--no-scripts', '--no-plugins', '--no-interaction'], $appPath);
        $gitPath = $appPath.'/vendor/pcteckserv/cms-core/.git';
        $this->assertStringStartsWith(realpath($this->directory).DIRECTORY_SEPARATOR, realpath($gitPath));
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($gitPath, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            chmod($item->getPathname(), $item->isDir() ? 0755 : 0644);
        }
        $this->assertTrue((new Filesystem)->deleteDirectory($gitPath));
        $this->assertDirectoryDoesNotExist($gitPath);
        $metadataPath = $appPath.'/vendor/composer/installed.json';
        $installed = json_decode(file_get_contents($metadataPath), true);
        $installed['packages'][0]['source']['reference'] = str_repeat('a', 40);
        $installed['packages'][0]['source']['url'] = $this->directory.'/origem-antiga-indisponivel.git';
        file_put_contents($metadataPath, json_encode($installed));
        $before = $this->preservedFiles();
        $this->renderRepair(composer: file_get_contents($composerPath));
        $failure = $this->request(['action' => 'install', 'backup_confirmed' => '1']);
        $this->assertStringContainsString('A operação falhou', $failure);
        $this->assertStringContainsString('GitDownloader.php', $failure);
        $this->assertStringContainsString('Metadados Git recuperados', $this->request(['action' => 'core-git', 'backup_confirmed' => '1']));
        $this->assertSame('1.1.0', file_get_contents($appPath.'/vendor/pcteckserv/cms-core/version.txt'));
        $installed = json_decode(file_get_contents($metadataPath), true);
        $this->assertSame('1.1.0', $installed['packages'][0]['version']);
        $this->assertSame($newReference, $installed['packages'][0]['source']['reference']);
        $this->assertStringContainsString('Operação concluída', $this->request(['action' => 'install', 'backup_confirmed' => '1']));
        $this->assertSame('1.1.0', file_get_contents($appPath.'/vendor/pcteckserv/cms-core/version.txt'));
        $this->assertSame($before, $this->preservedFiles());
        $this->assertFileDoesNotExist($appPath.'/artisan-commands.json');
    }

    private function preservedFiles(): array
    {
        $hashes = [];
        foreach (['.env', 'composer.json', 'composer.lock', 'storage/app/public/upload.txt', 'database/database.sqlite'] as $file) {
            $hashes[$file] = hash_file('sha256', $this->directory.'/app/'.$file);
        }

        return $hashes;
    }
}
