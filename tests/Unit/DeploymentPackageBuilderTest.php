<?php

namespace Tests\Unit;

use App\Services\DeploymentPackageBuilder;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Tests\TestCase;

class DeploymentPackageBuilderTest extends TestCase
{
    public function test_cria_processos_sem_shell_para_npm_e_php(): void
    {
        $builder = new DeploymentPackageBuilder(new Filesystem());
        $createBuildProcess = (new ReflectionClass($builder))->getMethod('createBuildProcess');

        $npmCommand = $createBuildProcess->invoke($builder, 'npm run build')->getCommandLine();
        $phpCommand = $createBuildProcess->invoke($builder, 'php artisan optimize')->getCommandLine();

        $this->assertStringContainsString(PHP_OS_FAMILY === 'Windows' ? 'npm.cmd' : 'npm', $npmCommand);
        $this->assertStringContainsString('run build', $npmCommand);
        $this->assertStringContainsString(PHP_BINARY, $phpCommand);
        $this->assertStringContainsString('artisan optimize', $phpCommand);
    }

    public function test_compilador_tenta_o_build_tres_vezes_por_defeito(): void
    {
        $this->assertSame(3, config('deploy.build_attempts'));
    }

    public function test_exclui_ficheiros_sensiveis_e_pastas_temporarias(): void
    {
        $builder = new DeploymentPackageBuilder(new Filesystem());
        $reflection = new ReflectionClass($builder);

        $normaliseExclusions = $reflection->getMethod('normaliseExclusions');
        $shouldExclude = $reflection->getMethod('shouldExclude');

        $exclude = $normaliseExclusions->invoke($builder, [
            '.env',
            'bootstrap/cache',
            'storage/app/deploy',
            'storage/logs',
            'node_modules',
            'public/hot',
        ]);

        $this->assertTrue($shouldExclude->invoke($builder, '.env', $exclude));
        $this->assertTrue($shouldExclude->invoke($builder, 'bootstrap/cache/config.php', $exclude));
        $this->assertTrue($shouldExclude->invoke($builder, 'storage/logs/laravel.log', $exclude));
        $this->assertTrue($shouldExclude->invoke($builder, 'storage/app/deploy/cmspcteckserv-deploy.zip', $exclude));
        $this->assertTrue($shouldExclude->invoke($builder, 'node_modules/vite/index.js', $exclude));
        $this->assertTrue($shouldExclude->invoke($builder, 'public/hot', $exclude));
        $this->assertFalse($shouldExclude->invoke($builder, 'app/Models/User.php', $exclude));
        $this->assertFalse($shouldExclude->invoke($builder, 'public/index.php', $exclude));
    }

    public function test_usa_assets_compilados_como_fallback_quando_existem(): void
    {
        config()->set('deploy.build_fallbacks', [
            'npm run build' => 'public/build/manifest.json',
        ]);

        $builder = new DeploymentPackageBuilder(new Filesystem());
        $useBuildFallback = (new ReflectionClass($builder))->getMethod('useBuildFallback');

        $this->assertTrue($useBuildFallback->invoke($builder, 'npm run build'));
        $this->assertFalse($useBuildFallback->invoke($builder, 'php artisan optimize'));
    }

    public function test_reconhece_se_os_assets_compilados_estao_atualizados(): void
    {
        $builder = new DeploymentPackageBuilder(new Filesystem());
        $buildFallbackIsFresh = (new ReflectionClass($builder))->getMethod('buildFallbackIsFresh');

        $this->assertTrue($buildFallbackIsFresh->invoke($builder, 'public/build/manifest.json'));
    }

    public function test_installer_prepara_as_pastas_gravaveis_do_laravel(): void
    {
        $stub = file_get_contents(resource_path('installer/installer.php.stub'));

        $this->assertStringContainsString("'bootstrap/cache'", $stub);
        $this->assertStringContainsString("'storage/framework/cache'", $stub);
        $this->assertStringContainsString("'storage/framework/sessions'", $stub);
        $this->assertStringContainsString("'storage/framework/views'", $stub);
        $this->assertStringContainsString("'storage/logs'", $stub);
        $this->assertStringContainsString('installer_prepare_writable_directories($appPath);', $stub);
    }

    public function test_installer_exige_opcao_explicita_para_limpar_a_base_de_dados(): void
    {
        $stub = file_get_contents(resource_path('installer/installer.php.stub'));

        $this->assertStringContainsString("installer_value('reset_database') === '1'", $stub);
        $this->assertStringContainsString("['migrate:fresh', '--force']", $stub);
        $this->assertStringContainsString("['migrate', '--force']", $stub);
        $this->assertStringContainsString('Elimina todas as tabelas existentes.', $stub);
    }

    public function test_inclui_pacotes_locais_configurados_no_deploy(): void
    {
        config()->set('deploy.local_package_overlays', [
            'pcteckserv/cms-core' => '../cmspcteckserv-core',
        ]);

        $builder = new DeploymentPackageBuilder(new Filesystem());
        $copies = (new ReflectionClass($builder))->getMethod('pathRepositoryCopies')->invoke($builder);
        $core = collect($copies)->firstWhere('destination', 'vendor/pcteckserv/cms-core');
        $pluginSource = collect($copies)->firstWhere('destination', 'packages/pcteckserv/cms-contact-forms');

        $this->assertNotNull($core);
        $this->assertSame(realpath(base_path('../cmspcteckserv-core')), $core['source']);
        $this->assertNotNull($pluginSource);
        $this->assertDirectoryExists($pluginSource['source']);
    }

    public function test_localiza_um_composer_phar_valido_para_o_deploy(): void
    {
        $builder = new DeploymentPackageBuilder(new Filesystem());
        $composerPhar = (new ReflectionClass($builder))->getMethod('composerPharPath')->invoke($builder);

        $this->assertNotNull($composerPhar);
        $this->assertFileExists($composerPhar);
        $this->assertSame('phar', pathinfo($composerPhar, PATHINFO_EXTENSION));
    }

    public function test_installer_remove_os_ficheiros_de_deploy_apos_sucesso(): void
    {
        $stub = file_get_contents(resource_path('installer/installer.php.stub'));

        $this->assertStringContainsString('function installer_remove_deployment_files', $stub);
        $this->assertStringContainsString('$publicPath.DIRECTORY_SEPARATOR.$archiveName', $stub);
        $this->assertStringContainsString('__FILE__', $stub);
        $this->assertStringContainsString('Os ficheiros de instalacao foram removidos automaticamente.', $stub);
    }

    public function test_installer_serializa_o_env_com_booleanos_e_escaping_seguros(): void
    {
        $stub = file_get_contents(resource_path('installer/installer.php.stub'));

        $this->assertStringContainsString("'APP_DEBUG' => false", $stub);
        $this->assertStringContainsString('if (is_bool($value))', $stub);
        $this->assertStringContainsString('FILTER_VALIDATE_URL', $stub);
        $this->assertStringContainsString('nao podem conter quebras de linha', $stub);
        $this->assertStringContainsString("'\\\\$'", $stub);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        include resource_path('installer/installer.php.stub');
        ob_end_clean();

        $password = 'abc$def\\ghi"jkl';
        $env = installer_env([
            'APP_DEBUG' => false,
            'DB_PASSWORD' => $password,
        ]);
        $parsed = \Dotenv\Dotenv::parse($env);

        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertSame('false', $parsed['APP_DEBUG']);
        $this->assertSame($password, $parsed['DB_PASSWORD']);
    }

    public function test_installer_configura_a_pasta_publica_real_no_bootstrap(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $installer = file_get_contents(resource_path('installer/installer.php.stub'));

        $this->assertStringContainsString("__DIR__.'/public_path.php'", $bootstrap);
        $this->assertStringContainsString('$app->usePublicPath($publicPath);', $bootstrap);
        $this->assertStringContainsString("'bootstrap'.DIRECTORY_SEPARATOR.'public_path.php'", $installer);
        $this->assertStringContainsString('var_export($publicPath, true)', $installer);
    }

    public function test_installer_remove_o_ficheiro_hot_do_vite_em_producao(): void
    {
        $installer = file_get_contents(resource_path('installer/installer.php.stub'));

        $this->assertContains('public/hot', config('deploy.exclude'));
        $this->assertStringContainsString("\$publicPath.DIRECTORY_SEPARATOR.'hot'", $installer);
        $this->assertStringContainsString('remover o ficheiro temporario do Vite', $installer);
    }

    public function test_installer_cria_superadministrador_sem_gravar_a_password_no_env(): void
    {
        $installer = file_get_contents(resource_path('installer/installer.php.stub'));

        $this->assertStringContainsString('name="admin_name"', $installer);
        $this->assertStringContainsString('name="admin_email"', $installer);
        $this->assertStringContainsString('name="admin_password"', $installer);
        $this->assertStringContainsString('name="admin_password_confirmation"', $installer);
        $this->assertStringContainsString('AdminUserSeeder', $installer);
        $this->assertStringContainsString("'ADMIN_USER_PASSWORD' => \$adminPassword", $installer);
        $this->assertStringNotContainsString("'ADMIN_USER_PASSWORD' => \$adminPassword,\n                'CACHE_STORE'", $installer);
        $this->assertStringContainsString('pelo menos 12 caracteres', $installer);
    }

    public function test_installer_pede_e_grava_o_token_github_sem_o_expor_no_formulario(): void
    {
        $installer = file_get_contents(resource_path('installer/installer.php.stub'));

        $this->assertStringContainsString('name="cms_github_token" type="password"', $installer);
        $this->assertStringContainsString("'CMS_GITHUB_TOKEN' => \$githubToken", $installer);
        $this->assertStringContainsString("\$_POST['cms_github_token'] ?? ''", $installer);
        $this->assertStringNotContainsString('github_pat_', $installer);
    }

    public function test_installer_pede_e_grava_dados_do_repositorio_starter(): void
    {
        $installer = file_get_contents(resource_path('installer/installer.php.stub'));
        $envExample = file_get_contents(base_path('.env.example'));

        $this->assertStringContainsString('name="starter_github_repository"', $installer);
        $this->assertStringContainsString('name="starter_github_token" type="password"', $installer);
        $this->assertStringContainsString("'STARTER_GITHUB_REPOSITORY' =>", $installer);
        $this->assertStringContainsString("'STARTER_GITHUB_TOKEN' => \$starterGithubToken", $installer);
        $this->assertStringContainsString('STARTER_GITHUB_REPOSITORY=', $envExample);
        $this->assertStringContainsString('STARTER_GITHUB_TOKEN=', $envExample);
    }
}
