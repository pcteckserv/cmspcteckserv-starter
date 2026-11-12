<?php

namespace Tests\Feature\Deployment;

use App\Models\User;
use App\Services\DeploymentPackageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Pcteckserv\CmsCore\Models\InstalledPlugin;
use Pcteckserv\CmsCore\Models\Role;
use Pcteckserv\CmsCore\Models\TwoFactorAuthentication;
use Pcteckserv\CmsCore\Services\Auth\TwoFactorManager;
use Tests\TestCase;

class DeploymentCompilerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_rota_de_compilacao_redireciona_visitante_para_login(): void
    {
        $this->get('/compilar')->assertRedirect(route('login'));
    }

    public function test_rota_de_compilacao_recusa_utilizador_que_nao_e_super_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/compilar')->assertForbidden();
        $this->actingAs($user)->post('/compilar', ['components' => ['database']])->assertForbidden();
    }

    public function test_rota_de_compilacao_gera_pacote_para_super_admin_em_producao(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $admin = $this->superAdmin();
        $disk = Storage::fake('local');
        $disk->put('deploy/cmspcteckserv-deploy.zip', 'conteudo-do-zip');
        $packagePath = $disk->path('deploy/cmspcteckserv-deploy.zip');

        $this->mock(DeploymentPackageBuilder::class)
            ->shouldReceive('build')
            ->once()
            ->with(['base'], null)
            ->andReturn([
                'path' => $packagePath,
                'relative_path' => 'deploy/cmspcteckserv-deploy.zip',
                'size' => 15,
                'created_at' => '2026-09-21 10:00:00',
                'warnings' => [],
            ]);

        $this->actingAs($admin)->withSession(['_token' => 'csrf-de-teste'])
            ->post('/compilar', ['components' => ['base'], '_token' => 'csrf-de-teste'])
            ->assertOk()
            ->assertDownload('cmspcteckserv-deploy.zip');
    }

    public function test_painel_apresenta_quatro_opcoes_sem_gerar_pacote(): void
    {
        $this->mock(DeploymentPackageBuilder::class)->shouldNotReceive('build');
        $this->actingAs($this->superAdmin())->get('/compilar')->assertOk()
            ->assertSee('value="base"', false)->assertSee('value="media"', false)
            ->assertSee('value="database"', false)->assertSee('value="plugins"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('minlength="12"', false);
    }

    public function test_post_de_visitante_nao_compila(): void
    {
        $this->mock(DeploymentPackageBuilder::class)->shouldNotReceive('build');
        $this->post('/compilar', ['components' => ['base']])->assertRedirect(route('login'));
    }

    public function test_valida_selecao_e_protecao_da_base_de_dados_sem_guardar_a_palavra_passe_na_sessao(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $admin = $this->superAdmin();
        $this->mock(DeploymentPackageBuilder::class)->shouldNotReceive('build');
        $this->actingAs($admin)->post('/compilar', [])->assertSessionHasErrors('components');
        $this->post('/compilar', ['components' => ['invalida']])->assertSessionHasErrors('components.0');
        $this->post('/compilar', ['components' => ['base', 'base']])->assertSessionHasErrors('components.0');
        $this->post('/compilar', ['components' => ['database']])->assertSessionHasErrors('password');
        $this->post('/compilar', [
            'components' => ['database'], 'password' => 'palavra-passe-de-teste',
            'password_confirmation' => 'confirmacao-diferente',
        ])->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
    }

    public function test_erro_de_compilacao_nao_expoe_dados_tecnicos(): void
    {
        $this->mock(DeploymentPackageBuilder::class)->shouldReceive('build')->once()
            ->andThrow(new \RuntimeException('Dado privado do processo'));
        $this->actingAs($this->superAdmin())->from('/compilar')
            ->post('/compilar', ['components' => ['base']])->assertRedirect('/compilar')
            ->assertSessionHasErrors('compile');
        $this->get('/compilar')->assertDontSee('Dado privado do processo');
    }

    public function test_compila_base_de_dados_com_palavra_passe_curta(): void
    {
        $disk = Storage::fake('local');
        $disk->put('deploy/curta.zip', 'pacote de teste');
        $this->mock(DeploymentPackageBuilder::class)->shouldReceive('build')->once()
            ->with(['database'], '1')
            ->andReturn(['path' => $disk->path('deploy/curta.zip')]);

        $this->actingAs($this->superAdmin())->post('/compilar', [
            'components' => ['database'], 'password' => '1', 'password_confirmation' => '1',
        ])->assertOk()->assertDownload('curta.zip');
    }

    public function test_transmite_apenas_componentes_validados_e_a_palavra_passe_ao_compilador(): void
    {
        $disk = Storage::fake('local');
        $disk->put('deploy/teste.zip', 'pacote de teste');
        $this->mock(DeploymentPackageBuilder::class)->shouldReceive('build')->once()
            ->with(['base', 'media', 'database', 'plugins'], ' palavra-passe-de-teste ')
            ->andReturn(['path' => $disk->path('deploy/teste.zip')]);
        $this->actingAs($this->superAdmin())->post('/compilar', [
            'components' => ['base', 'media', 'database', 'plugins'],
            'password' => ' palavra-passe-de-teste ', 'password_confirmation' => ' palavra-passe-de-teste ',
            'output_directory' => '../fora',
        ])->assertOk()->assertDownload('teste.zip');
    }

    public function test_exporta_apenas_plugins_instalados_sem_metadados_privados(): void
    {
        Storage::fake('local');
        InstalledPlugin::query()->create([
            'slug' => 'fixture', 'name' => 'fixture', 'package' => 'example/fixture', 'label' => 'Exemplo',
            'installed_version' => '1.2.3', 'status' => 'enabled', 'metadata' => ['token' => 'valor-privado-de-teste'],
        ]);
        $package = app(DeploymentPackageBuilder::class)->build(['plugins']);
        $archive = new \ZipArchive;
        $archive->open($package['path']);
        $plugins = json_decode($archive->getFromName('plugins.json'), true);
        $fixture = collect($plugins)->firstWhere('slug', 'fixture');
        $this->assertSame('enabled', $fixture['status']);
        $this->assertSame('1.2.3', $fixture['installed_version']);
        $this->assertArrayNotHasKey('metadata', $fixture);
        $this->assertFalse($archive->locateName('application.zip'));
        $archive->close();
    }

    public function test_compilar_exige_segundo_fator_quando_esta_ativo(): void
    {
        if (! class_exists(TwoFactorAuthentication::class)) {
            $this->markTestSkipped('O Core instalado não disponibiliza autenticação de dois fatores.');
        }

        $admin = $this->superAdmin();
        TwoFactorAuthentication::query()->create([
            'user_type' => $admin->getMorphClass(), 'user_id' => $admin->id,
            'secret' => 'SEGREDOAPENASPARATESTES', 'recovery_codes' => [],
            'version' => 'versao-de-teste', 'confirmed_at' => now(),
        ]);
        $this->mock(DeploymentPackageBuilder::class)->shouldNotReceive('build');
        $this->actingAs($admin)->post('/compilar', ['components' => ['base']])
            ->assertRedirect(route('two-factor.challenge'));
        $this->actingAs($admin)->withSession([TwoFactorManager::VERIFIED => 'versao-de-teste'])
            ->get('/compilar')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_compilacao_limita_a_frequencia_de_pedidos(): void
    {
        $this->actingAs($this->superAdmin());
        $this->mock(DeploymentPackageBuilder::class)->shouldNotReceive('build');
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post('/compilar', [])->assertSessionHasErrors('components');
        }
        $this->post('/compilar', [])->assertStatus(429);
    }

    public function test_compilacao_em_producao_exige_csrf(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->mock(DeploymentPackageBuilder::class)->shouldNotReceive('build');
        $this->actingAs($this->superAdmin())->post('/compilar', ['components' => ['base']])->assertStatus(419);
    }

    public function test_botao_de_compilacao_so_e_visivel_para_super_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Compilar');

        $this->actingAs($this->superAdmin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Compilar')
            ->assertSee(route('deploy.compile'), false);
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => 'core.super_admin'],
            ['name' => 'Super Admin', 'is_protected' => true],
        );

        $user = User::factory()->create();
        $user->cmsRoles()->sync([$role->id]);

        return $user;
    }
}
