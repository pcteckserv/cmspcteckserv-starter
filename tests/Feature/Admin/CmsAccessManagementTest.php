<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Pcteckserv\CmsCore\Models\Permission;
use Pcteckserv\CmsCore\Models\Role;
use Pcteckserv\CmsCore\Services\PermissionSynchronizer;
use Pcteckserv\CmsCore\Support\Permissions\PermissionRegistry;
use Tests\TestCase;

class CmsAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_consegue_listar_utilizadores(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Utilizadores');
    }

    public function test_utilizador_sem_permissao_recebe_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_pesquisa_de_utilizadores_funciona(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['name' => 'Maria Gestão', 'email' => 'maria@example.test']);
        User::factory()->create(['name' => 'João Editor', 'email' => 'joao@example.test']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'maria']))
            ->assertOk()
            ->assertSee('Maria Gestão')
            ->assertDontSee('João Editor');
    }

    public function test_criacao_de_utilizador_guarda_password_com_hash(): void
    {
        $admin = $this->superAdmin();
        $role = Role::query()->create(['name' => 'Editor', 'key' => 'core.editor']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Novo Utilizador',
                'email' => 'novo@example.test',
                'password' => 'Password-segura-123',
                'password_confirmation' => 'Password-segura-123',
                'state' => 'active',
                'roles' => [$role->id],
            ])
            ->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'novo@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('Password-segura-123', $user->password));
        $this->assertTrue($user->hasCmsRole('core.editor'));
    }

    public function test_email_duplicado_e_rejeitado(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['email' => 'duplicado@example.test']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Duplicado',
                'email' => 'duplicado@example.test',
                'password' => 'Password-segura-123',
                'password_confirmation' => 'Password-segura-123',
                'state' => 'active',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_utilizador_inativo_nao_consegue_autenticar(): void
    {
        $user = User::factory()->create([
            'email' => 'inativo@example.test',
            'password' => Hash::make('Password-segura-123'),
        ]);
        $user->cmsState()->create(['state' => 'inactive']);

        $this->post(route('login.store'), [
            'email' => 'inativo@example.test',
            'password' => 'Password-segura-123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registry_de_plugin_sincroniza_permissao_sem_duplicar(): void
    {
        $registry = app(PermissionRegistry::class);
        $registry->register([
            'blog.posts.view' => ['label' => 'Ver artigos', 'group' => 'Blog'],
        ]);

        app(PermissionSynchronizer::class)->sync();
        app(PermissionSynchronizer::class)->sync();

        $this->assertTrue($registry->has('blog.posts.view'));
        $this->assertSame(1, Permission::query()->where('key', 'blog.posts.view')->count());
    }

    public function test_role_herda_permissoes_e_gate_autoriza(): void
    {
        app(PermissionSynchronizer::class)->sync();

        $permission = Permission::query()->where('key', 'core.users.view')->firstOrFail();
        $role = Role::query()->create(['name' => 'Gestor', 'key' => 'core.manager']);
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create();
        $user->cmsRoles()->sync([$role->id]);

        $this->assertTrue($user->can('core.users.view'));
        $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
    }

    public function test_administrador_comum_nao_consegue_atribuir_super_admin(): void
    {
        app(PermissionSynchronizer::class)->sync();

        $manage = Permission::query()->where('key', 'core.users.manage_roles')->firstOrFail();
        $create = Permission::query()->where('key', 'core.users.create')->firstOrFail();
        $role = Role::query()->create(['name' => 'Admin', 'key' => 'core.admin']);
        $role->permissions()->sync([$manage->id, $create->id]);
        $superRole = Role::query()->create(['name' => 'Super Admin', 'key' => 'core.super_admin', 'is_protected' => true]);

        $admin = User::factory()->create();
        $admin->cmsRoles()->sync([$role->id]);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Tentativa',
                'email' => 'tentativa@example.test',
                'password' => 'Password-segura-123',
                'password_confirmation' => 'Password-segura-123',
                'state' => 'active',
                'roles' => [$superRole->id],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'tentativa@example.test')->firstOrFail();
        $this->assertFalse($created->hasCmsRole('core.super_admin'));
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
