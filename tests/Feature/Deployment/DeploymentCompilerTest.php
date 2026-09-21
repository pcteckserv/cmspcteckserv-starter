<?php

namespace Tests\Feature\Deployment;

use App\Services\DeploymentPackageBuilder;
use Tests\TestCase;

class DeploymentCompilerTest extends TestCase
{
    public function test_rota_de_compilacao_fica_indisponivel_quando_desativada(): void
    {
        config()->set('deploy.enabled', false);

        $this->get('/compilar')->assertNotFound();
    }

    public function test_rota_de_compilacao_exige_token_quando_configurado(): void
    {
        config()->set('deploy.enabled', true);
        config()->set('deploy.token', 'token-seguro');

        $this->get('/compilar')->assertForbidden();
    }

    public function test_rota_de_compilacao_gera_pacote_quando_autorizada(): void
    {
        config()->set('deploy.enabled', true);
        config()->set('deploy.token', 'token-seguro');

        $this->mock(DeploymentPackageBuilder::class)
            ->shouldReceive('build')
            ->once()
            ->andReturn([
                'path' => storage_path('app/deploy/cmspcteckserv-deploy.zip'),
                'relative_path' => 'deploy/cmspcteckserv-deploy.zip',
                'size' => 1024,
                'created_at' => '2026-09-21 10:00:00',
                'warnings' => ['Foram utilizados assets previamente compilados.'],
            ]);

        $this->get('/compilar?token=token-seguro')
            ->assertOk()
            ->assertSee('Pacote de deploy gerado')
            ->assertSee('Foram utilizados assets previamente compilados.')
            ->assertSee('deploy/cmspcteckserv-deploy.zip');
    }
}
