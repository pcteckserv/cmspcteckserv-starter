<?php

namespace Pcteckserv\CmsCore\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ArtisanCommandsController extends Controller
{
    public function index(): View
    {
        return view('cms-core::admin.laravel-commands.index', [
            'commands' => $this->commands(),
        ]);
    }

    public function run(string $command): RedirectResponse
    {
        $commands = $this->commands();

        if (! array_key_exists($command, $commands)) {
            abort(404);
        }

        $definition = $commands[$command];

        try {
            $exitCode = Artisan::call($definition['signature'], $definition['parameters']);
            $output = trim(Artisan::output());
        } catch (Throwable $exception) {
            return back()->with('artisan_command_error', sprintf(
                'O comando "%s" falhou: %s',
                $definition['label'],
                $exception->getMessage()
            ));
        }

        if ($exitCode !== 0) {
            return back()
                ->with('artisan_command_error', sprintf('O comando "%s" terminou com codigo %d.', $definition['label'], $exitCode))
                ->with('artisan_command_output', $output);
        }

        return back()
            ->with('artisan_command_success', sprintf('Comando "%s" executado com sucesso.', $definition['label']))
            ->with('artisan_command_output', $output ?: 'Comando concluido sem output.');
    }

    /**
     * @return array<string, array{label: string, description: string, signature: string, parameters: array<string, mixed>}>
     */
    private function commands(): array
    {
        return [
            'migrate' => [
                'label' => 'Executar migrations',
                'description' => 'Aplica migrations pendentes na base de dados.',
                'signature' => 'migrate',
                'parameters' => ['--force' => true],
            ],
            'migrate-status' => [
                'label' => 'Estado das migrations',
                'description' => 'Mostra que migrations ja foram executadas.',
                'signature' => 'migrate:status',
                'parameters' => [],
            ],
            'db-seed' => [
                'label' => 'Executar seeders',
                'description' => 'Corre os seeders configurados para preparar dados essenciais.',
                'signature' => 'db:seed',
                'parameters' => ['--force' => true],
            ],
            'storage-link' => [
                'label' => 'Criar link do storage',
                'description' => 'Cria o link publico para ficheiros guardados em storage/app/public.',
                'signature' => 'storage:link',
                'parameters' => [],
            ],
            'optimize-clear' => [
                'label' => 'Limpar cache geral',
                'description' => 'Limpa config, rotas, views, eventos e cache da aplicação.',
                'signature' => 'optimize:clear',
                'parameters' => [],
            ],
            'config-cache' => [
                'label' => 'Recriar cache da configuração',
                'description' => 'Recria a cache de configuração para produção.',
                'signature' => 'config:cache',
                'parameters' => [],
            ],
            'route-cache' => [
                'label' => 'Recriar cache das rotas',
                'description' => 'Recria a cache de rotas para produção.',
                'signature' => 'route:cache',
                'parameters' => [],
            ],
            'view-cache' => [
                'label' => 'Recriar cache das views',
                'description' => 'Compila e guarda as Blade views em cache.',
                'signature' => 'view:cache',
                'parameters' => [],
            ],
            'cache-clear' => [
                'label' => 'Limpar cache da aplicação',
                'description' => 'Limpa apenas a cache da aplicação.',
                'signature' => 'cache:clear',
                'parameters' => [],
            ],
            'queue-restart' => [
                'label' => 'Reiniciar workers da queue',
                'description' => 'Pede aos workers da queue para reiniciarem apos o job atual.',
                'signature' => 'queue:restart',
                'parameters' => [],
            ],
        ];
    }
}
