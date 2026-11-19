<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Pcteckserv\CmsCore\Database\Seeders\AdminUserSeeder;
use Pcteckserv\CmsCore\Models\InstalledPlugin;
use Pcteckserv\CmsCore\Plugins\PluginRepository;
use Tests\TestCase;

class PluginCatalogDistributionTest extends TestCase
{
    use RefreshDatabase;

    private string $catalogRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->catalogRoot = sys_get_temp_dir().'/catalog-test-'.bin2hex(random_bytes(12));
        File::makeDirectory($this->catalogRoot);
        config()->set('cms-plugins.local_repository', $this->catalogRoot);
        foreach ([
            ['cmspcteckserv-clientes-legacy', 'customers', 'cms-customers'],
            ['cmspcteckserv-clientes', 'customers', 'cms-customers'],
            ['cmspcteckserv-loja', 'shop', 'cms-shop'],
            ['cmspcteckserv-ecommerce', 'ecommerce', 'cms-ecommerce'],
            ['cmspcteckserv-customer-integrations', 'customer-integrations', 'cms-customer-integrations'],
        ] as [$folder, $slug, $package]) {
            File::makeDirectory($this->catalogRoot.'/'.$folder);
            File::put($this->catalogRoot.'/'.$folder.'/cms-plugin.json', json_encode(['slug' => $slug, 'package' => 'pcteckserv/'.$package, 'version' => ($slug === 'customers' ? '1.5.1' : '0.52.2'), 'label' => 'Nome antigo', 'version_constraint' => '^1.0']));
            File::put($this->catalogRoot.'/'.$folder.'/composer.json', json_encode(['require' => ['pcteckserv/cms-core' => '^3.0']]));
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->catalogRoot);
        parent::tearDown();
    }

    public function test_site_provider_preserves_the_configured_plugin_catalog(): void
    {
        foreach ([null, $this->catalogRoot] as $local) {
            config(['cms-plugins.local_repository' => $local, 'cms-plugins.repository_url' => 'https://example.test/plugins.git']);

            (new \App\Providers\AppServiceProvider($this->app))->register();

            $this->assertSame($local, config('cms-plugins.local_repository'));
            $this->assertSame('https://example.test/plugins.git', config('cms-plugins.repository_url'));
        }
    }

    public function test_catalog_uses_canonical_sources_and_preserves_installation_identity(): void
    {
        $repository = app(PluginRepository::class);
        $plugins = $repository->available();
        $this->assertSame(['customers', 'shop'], $plugins->pluck('slug')->all());
        $this->assertSame(['Clientes', 'Loja'], $plugins->pluck('label')->all());
        $customers = $repository->find('customers');
        $this->assertSame('cmspcteckserv-clientes', $customers->directory);
        $this->assertSame('pcteckserv/cms-customers', $customers->installData()['package']);
        $this->assertSame(str_replace('\\', '/', $this->catalogRoot.'/cmspcteckserv-clientes'), str_replace('\\', '/', $customers->repositoryPath));
        if (property_exists($customers, 'coreConstraint')) {
            $this->assertSame('^3.0', $customers->coreConstraint);
            $this->assertTrue($customers->compatible());
        }
        $this->assertNull($repository->find('ecommerce'));
        $this->assertNull($repository->find('customer-integrations'));
    }

    public function test_panel_hides_unused_legacy_records_but_preserves_real_installations_and_data(): void
    {
        // Este pacote sintético representa uma instalação Composer real.
        $this->partialMock(\Pcteckserv\CmsCore\Updates\ComposerInstalledPackageReader::class)
            ->shouldReceive('isAbsent')->with('pcteckserv/cms-customer-integrations')->andReturnFalse();
        config()->set(['cms-core.admin_user.name' => 'Administrador de teste', 'cms-core.admin_user.email' => 'catalog@example.test', 'cms-core.admin_user.password' => 'test-only-password-123']);
        $this->seed(AdminUserSeeder::class);
        $user = \App\Models\User::where('email', 'catalog@example.test')->firstOrFail();
        InstalledPlugin::create(['slug' => 'ecommerce', 'package' => 'pcteckserv/cms-ecommerce', 'name' => 'pcteckserv/cms-ecommerce', 'label' => 'Ecommerce antigo', 'status' => 'disabled']);
        InstalledPlugin::create(['slug' => 'customer-integrations', 'package' => 'pcteckserv/cms-customer-integrations', 'name' => 'pcteckserv/cms-customer-integrations', 'label' => 'Integração instalada', 'status' => 'disabled', 'installed_version' => '1.0.0']);
        $this->actingAs($user)->get(route('admin.plugins.index'))->assertOk()
            ->assertSee('Clientes')->assertSee('Loja')->assertDontSee('Ecommerce antigo')->assertSee('Integração instalada');
        $this->assertDatabaseHas('cms_plugins', ['slug' => 'ecommerce']);
        $this->assertDatabaseHas('cms_plugins', ['slug' => 'customer-integrations', 'installed_version' => '1.0.0']);
    }

    public function test_renamed_catalog_prefers_new_directory_and_can_read_old_installations(): void
    {
        $old = $this->catalogRoot.'/cmspcteckserv-clientes-byDL';
        File::copyDirectory($this->catalogRoot.'/cmspcteckserv-clientes', $old);
        $repository = app(PluginRepository::class);
        $this->assertSame('cmspcteckserv-clientes', $repository->find('customers')->directory);
        $this->assertCount(1, $repository->available()->where('package', 'pcteckserv/cms-customers'));
        $manifest = json_decode(File::get($this->catalogRoot.'/cmspcteckserv-clientes/cms-plugin.json'), true);
        $manifest['version'] = '1.0.0';
        File::put($this->catalogRoot.'/cmspcteckserv-clientes/cms-plugin.json', json_encode($manifest));
        $this->assertSame('cmspcteckserv-clientes-byDL', $repository->find('customers')->directory);
        File::deleteDirectory($this->catalogRoot.'/cmspcteckserv-clientes');
        $this->assertSame('cmspcteckserv-clientes-byDL', $repository->find('customers')->directory);
        $this->assertSame('Clientes', $repository->find('customers')->label);
        $this->assertSame('pcteckserv/cms-customers', $repository->find('customers')->installData()['package']);
    }
}
