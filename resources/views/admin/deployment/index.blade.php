@extends('cms-core::admin.layouts.app', ['title' => 'Compilar o site'])

@section('content')
    <h1 class="h3">Compilar o site</h1>
    <p class="text-secondary">Selecione o conteúdo a incluir no pacote. O instalador deteta as opções selecionadas e importa apenas esse conteúdo.</p>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('deploy.compile.store') }}" class="card">
        @csrf
        <div class="card-body">
            <fieldset>
                <legend class="h5">Conteúdo do pacote</legend>
                @foreach ([
                    'base' => ['Base', 'Aplicação, código, dependências, assets e instalador, como na compilação habitual.'],
                    'media' => ['Media', 'Ficheiros da pasta do gestor de media. Para levar também os registos e as associações, inclua a base de dados.'],
                    'database' => ['Base de dados', 'Estrutura e dados locais, incluindo conteúdos, utilizadores, opções e registo de plugins. Exportação protegida por palavra-passe.'],
                    'plugins' => ['Plugins', 'Lista de plugins instalados, versões e estado de ativação. Os ficheiros dos plugins acompanham a opção Base.'],
                ] as $value => [$label, $description])
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="components[]" id="compile-{{ $value }}" value="{{ $value }}" @checked(in_array($value, old('components', ['base']), true))>
                        <label class="form-check-label fw-semibold" for="compile-{{ $value }}">{{ $label }}</label>
                        <p class="text-secondary small mb-0">{{ $description }}</p>
                    </div>
                @endforeach
            </fieldset>
            <fieldset class="border-top pt-3">
                <legend class="h6">Proteção da base de dados</legend>
                <p class="small text-secondary">Preencha apenas se incluir a base de dados. Guarde esta palavra-passe: será necessária no instalador para restaurar os dados e os valores encriptados.</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="database-password">Palavra-passe da exportação</label>
                        <input class="form-control" id="database-password" name="password" type="password" maxlength="255" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="database-password-confirmation">Confirmar palavra-passe</label>
                        <input class="form-control" id="database-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                    </div>
                </div>
            </fieldset>
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit">Compilar e transferir</button></div>
    </form>
@endsection
