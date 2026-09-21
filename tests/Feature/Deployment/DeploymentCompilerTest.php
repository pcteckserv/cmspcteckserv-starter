<?php

namespace Tests\Feature\Deployment;

use App\Models\User;
use App\Services\DeploymentPackageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Pcteckserv\CmsCore\Models\Role;
use Tests\TestCase;

class DeploymentCompilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_rota_de_compilacao_redireciona_visitante_para_login(): void
    {
        $this->get('/compilar')->assertRedirect(route('login'));
    }

    public function test_rota_de_compilacao_recusa_utilizador_que_nao_e_super_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/compilar')->assertForbidden();
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
            ->andReturn([
                'path' => $packagePath,
                'relative_path' => 'deploy/cmspcteckserv-deploy.zip',
                'size' => 15,
                'created_at' => '2026-09-21 10:00:00',
                'warnings' => [],
            ]);

        $this->actingAs($admin)->get('/compilar')
            ->assertOk()
            ->assertDownload('cmspcteckserv-deploy.zip');
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
