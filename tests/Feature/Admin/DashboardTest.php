<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_nao_consegue_aceder_ao_dashboard_administrativo(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_utilizador_autenticado_consegue_aceder_ao_dashboard_administrativo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewIs('cms-core::admin.dashboard')
            ->assertSee('Painel de Administração')
            ->assertSee($user->email);
    }
}
