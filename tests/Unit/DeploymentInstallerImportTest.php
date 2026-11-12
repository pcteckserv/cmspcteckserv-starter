<?php

namespace Tests\Unit;

use App\Services\Deployment\DatabaseSnapshot;
use Dotenv\Dotenv;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Support\UsesDeploymentMysql;
use ZipArchive;

class DeploymentInstallerImportTest extends TestCase
{
    use UsesDeploymentMysql;

    private string $directory;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/deployment-import-'.bin2hex(random_bytes(8));
        $this->key = random_bytes(32);
        foreach (['public', 'app/bootstrap', 'app/database', 'app/vendor/composer'] as $directory) {
            mkdir($this->directory.'/'.$directory, 0755, true);
        }
        file_put_contents($this->directory.'/app/bootstrap/app.php', '<?php');
        file_put_contents($this->directory.'/app/.env', "APP_NAME=Destino\nAPP_KEY=chave-anterior\nOPCAO_LOCAL=preservada\n");
        file_put_contents($this->directory.'/app/vendor/composer/installed.json', json_encode(['packages' => [['name' => 'example/plugin']]]));
    }

    protected function tearDown(): void
    {
        $this->dropMysqlDatabase();
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_importa_base_media_e_base_de_dados_preservando_opcoes_contas_e_encriptacao(): void
    {
        $this->package(['base', 'media', 'database', 'plugins']);
        $html = $this->runInstaller($this->input());
        $this->assertStringNotContainsString('alert error', $html);
        $this->assertFileExists($this->directory.'/public/.cms_installed');
        $this->assertFileExists($this->directory.'/app/config/base.txt');
        $this->assertSame('media de teste', file_get_contents($this->directory.'/app/storage/app/public/cms/media/image.txt'));
        $destination = new PDO('sqlite:'.$this->directory.'/app/database/database.sqlite');
        $this->assertSame('opções da origem', $destination->query("SELECT value FROM cms_site_options WHERE name = 'site_name'")->fetchColumn());
        $encrypted = $destination->query("SELECT value FROM cms_site_options WHERE name = 'encrypted'")->fetchColumn();
        $env = Dotenv::parse(file_get_contents($this->directory.'/app/.env'));
        $this->assertSame('base64:'.base64_encode($this->key), $env['APP_KEY']);
        $this->assertSame('valor protegido', (new Encrypter(base64_decode(substr($env['APP_KEY'], 7)), 'AES-256-CBC'))->decryptString($encrypted));
        $this->assertSame('admin@example.test', $destination->query('SELECT email FROM users')->fetchColumn());
        $this->assertSame('enabled', $destination->query('SELECT status FROM cms_plugins')->fetchColumn());
        $this->assertFileDoesNotExist($this->directory.'/public/database.enc');
        $this->assertFileDoesNotExist($this->directory.'/public/deployment-runtime.php');
        $this->assertFileDoesNotExist($this->directory.'/public/installer.php');
        $destination = null;
    }

    public function test_importa_apenas_media_sem_exigir_credenciais_de_base_de_dados(): void
    {
        file_put_contents($this->directory.'/app/preservar.txt', 'ficheiro existente');
        $this->package(['media']);
        $html = $this->runInstaller(['app_path' => $this->directory.'/app', 'csrf_token' => 'csrf-de-teste']);
        $this->assertStringNotContainsString('alert error', $html);
        $this->assertSame('ficheiro existente', file_get_contents($this->directory.'/app/preservar.txt'));
        $this->assertFileExists($this->directory.'/app/storage/app/public/cms/media/image.txt');
        $this->assertStringContainsString('OPCAO_LOCAL=preservada', file_get_contents($this->directory.'/app/.env'));
        $this->assertFileDoesNotExist($this->directory.'/app/database/database.sqlite');
    }

    public function test_importa_plugins_separadamente_preservando_o_estado_e_a_versao(): void
    {
        $destination = $this->destination();
        $this->package(['plugins']);
        $html = $this->runInstaller($this->input());
        $this->assertStringNotContainsString('alert error', $html);
        $row = $destination->query('SELECT slug, installed_version, status FROM cms_plugins')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['slug' => 'example', 'installed_version' => '1.2.3', 'status' => 'enabled'], $row);
        $this->assertStringContainsString('OPCAO_LOCAL=preservada', file_get_contents($this->directory.'/app/.env'));
        $destination = null;
    }

    public function test_palavra_passe_errada_e_substituicao_sem_confirmacao_nao_alteram_o_destino(): void
    {
        $destination = $this->destination();
        $destination->exec("INSERT INTO cms_site_options VALUES ('site_name', 'destino')");
        $this->package(['database']);
        $html = $this->runInstaller(array_replace($this->input(), ['database_export_password' => 'palavra-passe-errada', 'reset_database' => '1']));
        $this->assertStringContainsString('alert error', $html);
        $this->assertStringContainsString('value="palavra-passe-errada"', $html);
        $this->assertSame('destino', $destination->query('SELECT value FROM cms_site_options')->fetchColumn());
        $html = $this->runInstaller($this->input());
        $this->assertStringContainsString('Confirme a substituição', $html);
        $this->assertSame('destino', $destination->query('SELECT value FROM cms_site_options')->fetchColumn());
        $html = $this->runInstaller(array_replace($this->input(), ['reset_database' => '1']));
        $this->assertStringNotContainsString('alert error', $html);
        $this->assertSame('opções da origem', $destination->query("SELECT value FROM cms_site_options WHERE name = 'site_name'")->fetchColumn());
        $destination = null;
    }

    public function test_recusa_payload_alterado_e_csrf_invalido(): void
    {
        $this->package(['media']);
        $html = $this->runInstaller(array_replace($this->input(), ['csrf_token' => 'invalido']));
        $this->assertStringContainsString('sessão do instalador expirou', $html);
        file_put_contents($this->directory.'/public/media.zip', 'alterado');
        $html = $this->runInstaller($this->input());
        $this->assertStringContainsString('em falta ou foi alterado', $html);
        $this->assertFileDoesNotExist($this->directory.'/public/.cms_installed');
        $this->assertFileDoesNotExist($this->directory.'/app/storage/app/public/cms/media/image.txt');
    }

    public function test_recusa_caminhos_zip_que_saiam_do_destino(): void
    {
        $this->package(['media'], true);
        $html = $this->runInstaller($this->input());
        $this->assertStringContainsString('caminho de ficheiro inseguro', $html);
        $this->assertFileDoesNotExist($this->directory.'/app/storage/app/fora.txt');
    }

    public function test_instalador_importa_com_palavra_passe_curta(): void
    {
        $this->package(['database'], databasePassword: '1');
        $html = $this->runInstaller(array_replace($this->input(), ['database_export_password' => '1']));

        $this->assertStringNotContainsString('alert error', $html);
        $destination = new PDO('sqlite:'.$this->directory.'/app/database/database.sqlite');
        $this->assertSame('opções da origem', $destination->query("SELECT value FROM cms_site_options WHERE name = 'site_name'")->fetchColumn());
    }

    public function test_instalador_migra_sqlite_para_mysql_com_migrations_laravel(): void
    {
        $destination = $this->mysqlDatabase();
        $this->package(['base', 'database'], mysqlApplication: true);
        $html = $this->runInstaller($this->mysqlInput());

        $this->assertStringNotContainsString('alert error', $html);
        $this->assertStringContainsString('migrada de SQLite para MySQL', $html);
        $this->assertSame('opções da origem', $destination->query("SELECT value FROM cms_site_options WHERE name = 'site_name'")->fetchColumn());
        $this->assertSame('admin@example.test', $destination->query('SELECT email FROM users WHERE id = 1')->fetchColumn());
        $this->assertSame('enabled', $destination->query('SELECT status FROM cms_plugins')->fetchColumn());
        $this->assertSame('2026_01_01_000000_create_mysql_tables', $destination->query('SELECT migration FROM migrations')->fetchColumn());
        $env = Dotenv::parse(file_get_contents($this->directory.'/app/.env'));
        $this->assertSame('mysql', $env['DB_CONNECTION']);
        $this->assertSame('base64:'.base64_encode($this->key), $env['APP_KEY']);
        $encrypted = $destination->query("SELECT value FROM cms_site_options WHERE name = 'encrypted'")->fetchColumn();
        $this->assertSame('valor protegido', (new Encrypter($this->key, 'AES-256-CBC'))->decryptString($encrypted));
        $this->assertFileExists($this->directory.'/public/.cms_installed');
        $this->assertFileDoesNotExist($this->directory.'/public/database.enc');
        $cachedConfig = require $this->directory.'/app/bootstrap/cache/config.php';
        $this->assertSame('mysql', $cachedConfig['database']['default']);
        $this->assertSame($env['DB_DATABASE'], $cachedConfig['database']['connections']['mysql']['database']);

        // Uma aplicação já instalada também aceita um pacote apenas com a base de dados.
        unlink($this->directory.'/public/.cms_installed');
        $this->package(['database']);
        $html = $this->runInstaller(array_replace($this->mysqlInput(), ['reset_database' => '1']));
        $this->assertStringNotContainsString('alert error', $html);
        $this->assertSame('opções da origem', $destination->query("SELECT value FROM cms_site_options WHERE name = 'site_name'")->fetchColumn());
    }

    public function test_migracao_no_instalador_exige_confirmacao_antes_de_executar_migrations(): void
    {
        $destination = $this->mysqlDatabase();
        $destination->exec('CREATE TABLE preservar (id INTEGER PRIMARY KEY) ENGINE=InnoDB');
        $destination->exec('INSERT INTO preservar VALUES (42)');
        $this->package(['base', 'database'], mysqlApplication: true);

        $html = $this->runInstaller($this->mysqlInput());

        $this->assertStringContainsString('Confirme a substituição', $html);
        $this->assertSame(42, (int) $destination->query('SELECT id FROM preservar')->fetchColumn());
        $this->assertSame(['preservar'], (new DatabaseSnapshot)->tables($destination));
        $this->assertStringContainsString('chave-anterior', file_get_contents($this->directory.'/app/.env'));
        $this->assertFileDoesNotExist($this->directory.'/public/.cms_installed');
    }

    public function test_migracao_exige_aplicacao_para_preparar_a_estrutura_mysql(): void
    {
        $this->package(['database']);
        $html = $this->runInstaller(array_replace($this->input(), [
            'db_connection' => 'mysql', 'db_database' => 'destino', 'db_username' => 'utilizador',
        ]));

        $this->assertStringContainsString('migração para MySQL exige a aplicação instalada', $html);
        $this->assertFileDoesNotExist($this->directory.'/public/.cms_installed');
        $this->assertStringContainsString('chave-anterior', file_get_contents($this->directory.'/app/.env'));
    }

    private function mysqlInput(): array
    {
        $parts = [];
        parse_str(str_replace(';', '&', substr(getenv('DEPLOY_TEST_MYSQL_DSN'), 6)), $parts);

        return array_replace($this->input(), [
            'db_connection' => 'mysql', 'db_host' => $parts['host'] ?? '127.0.0.1', 'db_port' => $parts['port'] ?? '3306',
            'db_database' => $this->mysqlDatabase()->query('SELECT DATABASE()')->fetchColumn(),
            'db_username' => getenv('DEPLOY_TEST_MYSQL_USER') ?: '', 'db_password' => getenv('DEPLOY_TEST_MYSQL_PASSWORD') ?: '',
        ]);
    }

    private function package(array $components, bool $unsafeMedia = false, string $databasePassword = ' palavra-passe-de-teste ', bool $mysqlApplication = false): void
    {
        $public = $this->directory.'/public';
        $files = [];
        $plugin = ['slug' => 'example', 'name' => 'example', 'package' => 'example/plugin', 'label' => 'Exemplo', 'description' => null, 'provider' => null, 'installed_version' => '1.2.3', 'status' => 'enabled'];
        if (in_array('base', $components, true)) {
            $zip = new ZipArchive;
            $zip->open($public.'/application.zip', ZipArchive::CREATE);
            $zip->addFromString('public/index.php', '<?php');
            $zip->addFromString('bootstrap/app.php', '<?php');
            $zip->addFromString('config/base.txt', 'base de teste');
            if ($mysqlApplication) {
                $autoload = var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true);
                $zip->addFromString('artisan', '<?php require '.$autoload.'; $app = require __DIR__."/bootstrap/app.php"; exit($app->handleCommand(new Symfony\Component\Console\Input\ArgvInput));');
                $zip->addFromString('bootstrap/app.php', '<?php return Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))->withCommands()->create();');
                $zip->addFromString('composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
                $zip->addFromString('resources/views/.gitkeep', '');
                $zip->addFromString('config/database.php', file_get_contents(dirname(__DIR__).'/Fixtures/Deployment/mysql-database.php'));
                $zip->addFromString('database/migrations/2026_01_01_000000_create_mysql_tables.php', file_get_contents(dirname(__DIR__).'/Fixtures/Deployment/create_mysql_tables.php'));
            }
            $zip->close();
            $files[] = 'application.zip';
        }
        if (in_array('database', $components, true)) {
            $source = $this->schema(new PDO('sqlite::memory:'));
            $source->exec("INSERT INTO cms_site_options VALUES ('site_name', 'opções da origem')");
            $source->prepare('INSERT INTO cms_site_options VALUES (?, ?)')->execute(['encrypted', (new Encrypter($this->key, 'AES-256-CBC'))->encryptString('valor protegido')]);
            $source->exec("INSERT INTO users VALUES (1, 'admin@example.test')");
            $source->exec("INSERT INTO cms_plugins (slug, name, package, label, installed_version, status) VALUES ('example', 'example', 'example/plugin', 'Exemplo', '1.2.3', 'enabled')");
            (new DatabaseSnapshot)->export($source, $public.'/database.enc', $databasePassword, 'base64:'.base64_encode($this->key));
            $files[] = 'database.enc';
        }
        if (in_array('media', $components, true)) {
            $zip = new ZipArchive;
            $zip->open($public.'/media.zip', ZipArchive::CREATE);
            $zip->addFromString($unsafeMedia ? '../fora.txt' : 'cms/media/image.txt', 'media de teste');
            $zip->close();
            $files[] = 'media.zip';
        }
        if (in_array('plugins', $components, true)) {
            file_put_contents($public.'/plugins.json', json_encode([$plugin], JSON_THROW_ON_ERROR));
            $files[] = 'plugins.json';
        }
        copy(dirname(__DIR__, 2).'/app/Services/Deployment/DatabaseSnapshot.php', $public.'/deployment-runtime.php');
        $files[] = 'deployment-runtime.php';
        $manifest = ['format' => 2, 'components' => $components, 'files' => [], 'media' => ['root' => 'storage/app/public', 'directory' => 'cms/media']];
        foreach ($files as $file) {
            $manifest['files'][$file] = hash_file('sha256', $public.'/'.$file);
        }
        $stub = file_get_contents(dirname(__DIR__, 2).'/resources/installer/installer.php.stub');
        file_put_contents($public.'/installer.php', str_replace(['{{ application_archive_name }}', '{{ deployment_manifest }}'], ['application.zip', base64_encode(json_encode($manifest))], $stub));
    }

    private function destination(): PDO
    {
        return $this->schema(new PDO('sqlite:'.$this->directory.'/app/database/database.sqlite'));
    }

    private function schema(PDO $database): PDO
    {
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE cms_site_options (name TEXT PRIMARY KEY, value TEXT)');
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT)');
        $database->exec('CREATE TABLE cms_plugins (id INTEGER PRIMARY KEY, slug TEXT UNIQUE, name TEXT, package TEXT, label TEXT, description TEXT, provider TEXT, installed_version TEXT, status TEXT, installed_at TEXT)');

        return $database;
    }

    private function input(): array
    {
        return [
            'app_path' => $this->directory.'/app', 'app_url' => 'https://example.test', 'db_connection' => 'sqlite',
            'database_export_password' => ' palavra-passe-de-teste ', 'csrf_token' => 'csrf-de-teste',
            'cms_core_repository_url' => 'https://github.com/example/core.git', 'cms_plugins_repository_url' => 'https://github.com/example/plugins.git',
            'cms_github_token' => 'fake-token-fixture', 'starter_github_repository' => 'example/site', 'starter_github_token' => 'fake-token-fixture',
        ];
    }

    private function runInstaller(array $input): string
    {
        $process = new Process([PHP_BINARY, '-r',
            '$_SESSION["cms_installer_csrf"] = "csrf-de-teste"; $_POST = json_decode($argv[1], true); $_SERVER["REQUEST_METHOD"] = "POST"; require $argv[2];',
            json_encode($input, JSON_THROW_ON_ERROR), $this->directory.'/public/installer.php',
        ]);
        $process->setTimeout(20);
        $process->mustRun();
        $this->assertSame('', $process->getErrorOutput());

        return $process->getOutput();
    }
}
