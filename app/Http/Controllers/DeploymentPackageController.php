<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompileDeploymentRequest;
use App\Services\DeploymentPackageBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DeploymentPackageController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isCmsSuperAdmin(), 403);

        return view('admin.deployment.index');
    }

    public function store(CompileDeploymentRequest $request, DeploymentPackageBuilder $builder): Response|RedirectResponse
    {
        try {
            $package = $builder->build(
                $request->validated('components'),
                $request->validated('password'),
            );
        } catch (\Throwable $exception) {
            // A mensagem técnica pode conter caminhos ou informação do processo de build.
            Log::error('Falha na compilação do pacote.', ['tipo' => $exception::class]);

            return back()->withErrors(['compile' => 'Não foi possível compilar o pacote. Verifique as dependências e o espaço disponível e tente novamente.']);
        }

        return response()->download(
            $package['path'],
            basename($package['path']),
        )->deleteFileAfterSend(true);
    }
}
