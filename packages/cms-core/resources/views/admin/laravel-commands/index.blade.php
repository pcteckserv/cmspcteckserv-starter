@extends('cms-core::admin.layouts.app', ['title' => 'Comandos Laravel'])

@section('content')
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Comandos Laravel</h1>
            <p class="text-secondary mb-0">Executa comandos essenciais do Laravel atraves de uma rota protegida do painel.</p>
        </div>

        <span class="badge text-bg-success align-self-start">Protegido por autenticação</span>
    </div>

    @if (session('artisan_command_success'))
        <div class="alert alert-success">{{ session('artisan_command_success') }}</div>
    @endif

    @if (session('artisan_command_error'))
        <div class="alert alert-danger">{{ session('artisan_command_error') }}</div>
    @endif

    @if (session('artisan_command_output'))
        <div class="bg-dark text-white rounded-2 p-3 mb-4">
            <div class="small text-white-50 mb-2">Output</div>
            <pre class="mb-0 text-white" style="white-space: pre-wrap;">{{ session('artisan_command_output') }}</pre>
        </div>
    @endif

    <div class="bg-white border rounded-2">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Comando</th>
                        <th scope="col">Descricao</th>
                        <th scope="col">Artisan</th>
                        <th scope="col" class="text-end">Acao</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($commands as $key => $command)
                        <tr>
                            <td class="fw-semibold">{{ $command['label'] }}</td>
                            <td class="text-secondary">{{ $command['description'] }}</td>
                            <td><code>php artisan {{ $command['signature'] }}</code></td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.laravel-commands.run', ['command' => $key]) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-sm" type="submit">Executar</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
