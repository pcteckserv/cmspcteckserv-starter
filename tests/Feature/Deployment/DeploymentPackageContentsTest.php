<?php

namespace Tests\Feature\Deployment;

use App\Services\DeploymentPackageBuilder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class DeploymentPackageContentsTest extends TestCase
{
    private string $directory;

    private string $originalBase;

    private string $originalStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalBase = base_path();
        $this->originalStorage = storage_path();
        $this->directory = sys_get_temp_dir().'/deployment-package-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        foreach (['resources/installer', 'app/Services/Deployment', 'storage/app/public/cms/media', 'database', 'public'] as $path) {
            $files->ensureDirectoryExists($this->directory.'/'.$path);
        }
        $files->copy(resource_path('installer/installer.php.stub'), $this->directory.'/resources/installer/installer.php.stub');
        $files->copy(app_path('Services/Deployment/DatabaseSnapshot.php'), $this->directory.'/app/Services/Deployment/DatabaseSnapshot.php');
        $files->put($this->directory.'/public/index.php', '<?php');
        $files->put($this->directory.'/storage/app/public/cms/media/imagem.txt', 'media de teste');
        $files->put($this->directory.'/.env', 'NAO_INCLUIR=exemplo');
        $files->put($this->directory.'/database/database.sqlite', 'nao incluir');
        $this->app->setBasePath($this->directory);
        $this->app->useStoragePath($this->directory.'/storage');
        config()->set([
            'deploy.build_commands' => [], 'deploy.local_package_overlays' => [],
            'filesystems.disks.local' => ['driver' => 'local', 'root' => $this->directory.'/storage/app/private'],
            'filesystems.disks.public' => ['driver' => 'local', 'root' => $this->directory.'/storage/app/public'],
            'cms-core.media.disk' => 'public', 'cms-core.media.directory' => 'cms/media',
        ]);
        Storage::forgetDisk(['local', 'public']);
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBase);
        $this->app->useStoragePath($this->originalStorage);
        Storage::forgetDisk(['local', 'public']);
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_base_exclui_media_base_de_dados_e_ficheiros_sensiveis(): void
    {
        $package = app(DeploymentPackageBuilder::class)->build(['base']);
        $zip = new ZipArchive;
        $zip->open($package['path']);
        $manifest = json_decode($zip->getFromName('deployment.json'), true);
        $this->assertSame(['base'], $manifest['components']);
        $this->assertFalse($zip->locateName('media.zip'));
        $this->assertFalse($zip->locateName('database.enc'));
        $innerPath = $this->directory.'/application.zip';
        file_put_contents($innerPath, $zip->getFromName('application.zip'));
        $zip->close();
        $zip->open($innerPath);
        $this->assertNotFalse($zip->locateName('public/index.php'));
        $this->assertFalse($zip->locateName('.env'));
        $this->assertFalse($zip->locateName('database/database.sqlite'));
        $this->assertFalse($zip->locateName('storage/app/public/cms/media/imagem.txt'));
        $zip->close();
    }

    public function test_media_so_inclui_a_pasta_selecionada_e_compilacoes_nao_se_sobrepoem(): void
    {
        $builder = app(DeploymentPackageBuilder::class);
        $first = $builder->build(['media']);
        $second = $builder->build(['media']);
        $this->assertNotSame($first['path'], $second['path']);
        $this->assertFileExists($first['path']);
        $zip = new ZipArchive;
        $zip->open($first['path']);
        $this->assertFalse($zip->locateName('application.zip'));
        $manifest = json_decode($zip->getFromName('deployment.json'), true);
        $this->assertSame(['media'], $manifest['components']);
        $this->assertSame('storage/app/public', $manifest['media']['root']);
        file_put_contents($this->directory.'/media.zip', $zip->getFromName('media.zip'));
        $zip->close();
        $this->assertSame($manifest['files']['media.zip'], hash_file('sha256', $this->directory.'/media.zip'));
        $zip->open($this->directory.'/media.zip');
        $this->assertSame('media de teste', $zip->getFromName('cms/media/imagem.txt'));
        $zip->close();
    }

    public function test_compilacao_usa_limite_proprio_e_repoe_o_limite_do_pedido(): void
    {
        $originalLimit = (int) ini_get('max_execution_time');
        config()->set('deploy.execution_timeout', 1800);
        $files = \Mockery::mock(Filesystem::class)->makePartial();
        $files->shouldReceive('size')->once()->andReturnUsing(function (string $path): int {
            $this->assertSame(1800, (int) ini_get('max_execution_time'));

            return filesize($path);
        });

        try {
            set_time_limit(120);
            $package = (new DeploymentPackageBuilder($files))->build(['media']);

            $this->assertFileExists($package['path']);
            $this->assertSame(120, (int) ini_get('max_execution_time'));
        } finally {
            set_time_limit($originalLimit);
        }
    }

    public function test_compilacao_repoe_o_limite_apos_falha_e_nao_deixa_pacotes_parciais(): void
    {
        $originalLimit = (int) ini_get('max_execution_time');
        config()->set('filesystems.disks.public.driver', 's3');

        try {
            set_time_limit(120);

            try {
                app(DeploymentPackageBuilder::class)->build(['media']);
                $this->fail('A compilação devia rejeitar um disco remoto.');
            } catch (RuntimeException $exception) {
                $this->assertSame('A exportação de media exige um disco local.', $exception->getMessage());
            }

            $this->assertSame(120, (int) ini_get('max_execution_time'));
            $this->assertSame([], Storage::disk('local')->allFiles('deploy'));
            $this->assertSame([], Storage::disk('local')->allDirectories('deploy'));
        } finally {
            set_time_limit($originalLimit);
        }
    }

    public function test_pacote_de_base_de_dados_nao_inclui_dados_nem_chaves_em_texto_simples(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->exec('CREATE TABLE options (name TEXT, value TEXT)');
        $database->exec("INSERT INTO options VALUES ('nome', 'conteudo privado de teste')");
        DB::shouldReceive('connection')->once()->andReturn(new class($database)
        {
            public function __construct(private PDO $database) {}

            public function getPdo(): PDO
            {
                return $this->database;
            }
        });
        config()->set('app.key', 'chave de teste da exportacao');
        $package = app(DeploymentPackageBuilder::class)->build(['database'], 'palavra-passe-de-teste');
        $zip = new ZipArchive;
        $zip->open($package['path']);
        $this->assertFalse($zip->locateName('application.zip'));
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $contents = $zip->getFromIndex($index);
            $this->assertStringNotContainsString('conteudo privado de teste', $contents);
            $this->assertStringNotContainsString('chave de teste da exportacao', $contents);
            $this->assertStringNotContainsString('palavra-passe-de-teste', $contents);
        }
        $zip->close();
    }
}
