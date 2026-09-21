<?php

namespace Tests\Feature\Deployment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Pcteckserv\CmsCore\Database\Seeders\AdminUserSeeder;
use Tests\TestCase;

class InstallerAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_um_superadministrador_ativo_com_password_segura(): void
    {
        config()->set('cms-core.admin_user', [
            'name' => 'Administrador Inicial',
            'email' => 'admin@example.test',
            'password' => 'Password-segura-123',
        ]);

        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

        $this->assertSame('Administrador Inicial', $admin->name);
        $this->assertTrue(Hash::check('Password-segura-123', $admin->password));
        $this->assertTrue($admin->hasCmsRole('core.super_admin'));
        $this->assertSame('active', $admin->cmsState?->state);
    }
}
