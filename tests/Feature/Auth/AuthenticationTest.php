<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_pode_ser_visualizado_por_visitantes(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('cms-core::auth.login')
            ->assertSee('Iniciar sessão');
    }

    public function test_utilizador_consegue_iniciar_sessao_com_credenciais_validas(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('palavra-passe-segura'),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'palavra-passe-segura',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_utilizador_nao_consegue_iniciar_sessao_com_credenciais_invalidas(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('palavra-passe-segura'),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'errada',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_termina_a_sessao_do_utilizador(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
