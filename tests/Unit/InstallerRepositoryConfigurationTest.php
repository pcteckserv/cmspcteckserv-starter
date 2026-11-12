<?php

namespace Tests\Unit;

use DOMDocument;
use DOMXPath;
use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use ZipArchive;

class InstallerRepositoryConfigurationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/cms-installer-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/public', 0755, true);
        $installer = file_get_contents(dirname(__DIR__, 2).'/resources/installer/installer.php.stub');
        file_put_contents($this->directory.'/public/installer.php', str_replace('{{ application_archive_name }}', 'application.zip', $installer));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->directory.'/public/application.zip', ZipArchive::CREATE));
        $zip->addFromString('public/index.php', '<?php');
        $zip->addEmptyDir('bootstrap');
        $zip->close();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_grava_os_cinco_valores_introduzidos_no_env(): void
    {
        $values = $this->validInput();
        $html = $this->runInstaller($values);

        $this->assertFileExists($this->directory.'/app/.env', $html);
        $env = Dotenv::parse(file_get_contents($this->directory.'/app/.env'));

        foreach (['cms_core_repository_url', 'cms_github_token', 'starter_github_repository', 'starter_github_token', 'cms_plugins_repository_url'] as $field) {
            $this->assertSame($values[$field], $env[strtoupper($field)]);
        }

        $this->assertFileExists($this->directory.'/public/.cms_installed');
        $this->assertFileDoesNotExist($this->directory.'/public/installer.php');
    }

    public function test_mantem_a_conversao_do_starter_no_formato_owner_repo(): void
    {
        $html = $this->runInstaller(array_replace($this->validInput(), ['starter_github_repository' => 'example/site']));

        $this->assertFileExists($this->directory.'/app/.env', $html);
        $env = Dotenv::parse(file_get_contents($this->directory.'/app/.env'));
        $this->assertSame('https://github.com/example/site.git', $env['STARTER_GITHUB_REPOSITORY']);
    }

    #[DataProvider('invalidConfiguration')]
    public function test_recusa_configuracao_invalida_sem_criar_env(string $field, mixed $value): void
    {
        $html = $this->runInstaller(array_replace($this->validInput(), [$field => $value]));

        $this->assertStringContainsString('alert error', $html);
        $this->assertFileDoesNotExist($this->directory.'/app/.env');
        $this->assertFileDoesNotExist($this->directory.'/public/.cms_installed');
        foreach (['cms_github_token', 'starter_github_token'] as $token) {
            $input = array_replace($this->validInput(), [$field => $value]);
            $this->assertPasswordValue($html, $token, is_string($input[$token]) ? $input[$token] : '');
        }
    }

    public static function invalidConfiguration(): iterable
    {
        foreach (['cms_core_repository_url', 'cms_plugins_repository_url'] as $field) {
            foreach (['', 'invalid', 'http://github.com/example/repo.git', 'https://github.com/', 'https://user:secret@github.com/example/repo.git', 'https://github.com/example/repo.git?token=secret', 'https://github.com/example/repo.git#fragment', "https://github.com/example/repo.git\nINJECTED=true", 'https://github.com/example/'.str_repeat('a', 2048), ['invalid']] as $index => $value) {
                yield $field.'-'.$index => [$field, $value];
            }
        }

        foreach (['cms_github_token', 'starter_github_token'] as $field) {
            foreach (['', str_repeat('a', 256), "token\nINJECTED=true", ['invalid']] as $index => $value) {
                yield $field.'-'.$index => [$field, $value];
            }
        }
    }

    public function test_formulario_apresenta_cinco_campos_e_nao_reapresenta_tokens(): void
    {
        $html = $this->runInstaller($this->validInput(), 'GET');

        foreach (['cms_core_repository_url', 'cms_github_token', 'starter_github_repository', 'starter_github_token', 'cms_plugins_repository_url'] as $field) {
            $this->assertStringContainsString('name="'.$field.'"', $html);
            $this->assertStringContainsString(strtoupper($field), $html);
        }

        foreach (['cms_github_token', 'starter_github_token'] as $field) {
            $this->assertStringContainsString('name="'.$field.'" type="password"', $html);
            $this->assertPasswordValue($html, $field, '');
        }

        $this->assertFileDoesNotExist($this->directory.'/app/.env');
    }

    public function test_escapa_o_url_reapresentado_apos_erro(): void
    {
        $html = $this->runInstaller(array_replace($this->validInput(), [
            'cms_core_repository_url' => '"><script>alert(1)</script>',
        ]));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_mantem_tokens_e_palavras_passe_apos_erro_sem_alterar_caracteres(): void
    {
        $input = array_replace($this->validInput(), ['app_url' => 'invalido']);
        foreach (['db_password', 'cms_github_token', 'starter_github_token', 'admin_password', 'admin_password_confirmation'] as $field) {
            $input[$field] = '  '.$field.'"><script>alert(1)</script>&ç  ';
        }
        $html = $this->runInstaller($input);

        $this->assertStringContainsString('alert error', $html);
        foreach (['db_password', 'cms_github_token', 'starter_github_token', 'admin_password', 'admin_password_confirmation'] as $field) {
            $this->assertPasswordValue($html, $field, $input[$field]);
        }
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertFileDoesNotExist($this->directory.'/app/.env');
    }

    public function test_mantem_valores_apos_erro_csrf_sem_executar_instalacao(): void
    {
        $html = $this->runInstaller(array_replace($this->validInput(), ['csrf_token' => 'invalido']));

        $this->assertStringContainsString('sessão do instalador expirou', $html);
        foreach (['db_password', 'cms_github_token', 'starter_github_token', 'admin_password', 'admin_password_confirmation'] as $field) {
            $this->assertPasswordValue($html, $field, $this->validInput()[$field] ?? '');
        }
        $this->assertFileDoesNotExist($this->directory.'/app/.env');
    }

    private function assertPasswordValue(string $html, string $field, string $expected): void
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $inputs = (new DOMXPath($document))->query('//input[@name="'.$field.'"]');
        $this->assertCount(1, $inputs);
        $this->assertSame('password', $inputs->item(0)->getAttribute('type'));
        $this->assertSame($expected, $inputs->item(0)->getAttribute('value'));
    }

    private function validInput(): array
    {
        return [
            'app_path' => $this->directory.'/app',
            'app_url' => 'https://example.test',
            'db_database' => 'cms',
            'db_connection' => 'sqlite',
            'db_username' => 'cms',
            'cms_core_repository_url' => 'https://github.com/example/custom-core.git',
            'cms_plugins_repository_url' => 'https://github.com/example/custom-plugins.git',
            'cms_github_token' => 'fake-cms-$token\\with"quotes',
            'starter_github_repository' => 'https://github.com/example/custom-site.git',
            'starter_github_token' => 'fake-starter-$token\\with"quotes',
            'admin_email' => 'admin@example.test',
            'admin_password' => 'Example-password-123',
            'admin_password_confirmation' => 'Example-password-123',
            'csrf_token' => 'csrf-de-teste',
        ];
    }

    private function runInstaller(array $input, string $method = 'POST'): string
    {
        $process = new Process([
            PHP_BINARY,
            '-r',
            '$_SESSION["cms_installer_csrf"] = "csrf-de-teste"; $_POST = json_decode($argv[1], true); $_SERVER["REQUEST_METHOD"] = $argv[2]; require $argv[3];',
            json_encode($input, JSON_THROW_ON_ERROR),
            $method,
            $this->directory.'/public/installer.php',
        ]);
        $process->setTimeout(15);
        $process->mustRun();

        $this->assertSame('', $process->getErrorOutput());

        return $process->getOutput();
    }
}
