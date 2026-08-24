<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Pcteckserv\CmsCore\Database\Seeders\AdminUserSeeder;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_administrador_com_model_configurado(): void
    {
        config([
            'cms-core.user_model' => User::class,
            'cms-core.admin_user.name' => 'Administrador Teste',
            'cms-core.admin_user.email' => 'admin-configurado@example.test',
            'cms-core.admin_user.password' => 'palavra-passe-segura',
        ]);

        $this->seed(AdminUserSeeder::class);

        $user = User::query()->where('email', 'admin-configurado@example.test')->firstOrFail();

        $this->assertSame('Administrador Teste', $user->name);
        $this->assertTrue(Hash::check('palavra-passe-segura', $user->password));
    }

    public function test_seeder_exige_email_configurado(): void
    {
        config([
            'cms-core.admin_user.email' => null,
            'cms-core.admin_user.password' => 'palavra-passe-segura',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configure ADMIN_USER_EMAIL');

        $this->seed(AdminUserSeeder::class);
    }

    public function test_seeder_exige_password_configurada(): void
    {
        config([
            'cms-core.admin_user.email' => 'admin@example.test',
            'cms-core.admin_user.password' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configure ADMIN_USER_PASSWORD');

        $this->seed(AdminUserSeeder::class);
    }
}
