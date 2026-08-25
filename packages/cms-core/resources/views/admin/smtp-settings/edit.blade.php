@extends('cms-core::admin.layouts.app', ['title' => 'SMTP'])

@section('content')
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">SMTP</h1>
            <p class="text-secondary mb-0">Configuração global de email usada pelos elementos do site.</p>
        </div>
    </div>

    @if (session('cms_smtp_success'))
        <div class="alert alert-success">{{ session('cms_smtp_success') }}</div>
    @endif

    @if (session('cms_smtp_error'))
        <div class="alert alert-danger">{{ session('cms_smtp_error') }}</div>
    @endif

    <form class="bg-white border rounded-2 p-4 cms-settings-form mb-4" method="POST" action="{{ route('admin.smtp-settings.update') }}">
        @csrf
        @method('PUT')

        <div class="row g-3 align-items-start mb-4">
            <div class="col-lg-2 fw-semibold">Estado</div>
            <div class="col-lg-6">
                <div class="form-check form-switch">
                    <input id="smtp_enabled" name="smtp_enabled" type="checkbox" class="form-check-input @error('smtp_enabled') is-invalid @enderror" value="1" @checked(old('smtp_enabled', $options['smtp_enabled']) == '1')>
                    <label class="form-check-label" for="smtp_enabled">Ativar envio por SMTP</label>
                    @error('smtp_enabled')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-text">Quando ativo, estes dados passam a ser a configuração global de email do site.</div>
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="smtp_host">Servidor SMTP</label>
            <div class="col-lg-5">
                <input id="smtp_host" name="smtp_host" type="text" class="form-control @error('smtp_host') is-invalid @enderror" value="{{ old('smtp_host', $options['smtp_host']) }}" placeholder="smtp.exemplo.pt">
                @error('smtp_host')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="smtp_port">Porta</label>
            <div class="col-lg-2">
                <input id="smtp_port" name="smtp_port" type="number" min="1" max="65535" class="form-control @error('smtp_port') is-invalid @enderror" value="{{ old('smtp_port', $options['smtp_port']) }}">
                @error('smtp_port')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <label class="col-lg-1 col-form-label fw-semibold" for="smtp_encryption">Segurança</label>
            <div class="col-lg-2">
                <select id="smtp_encryption" name="smtp_encryption" class="form-select @error('smtp_encryption') is-invalid @enderror">
                    @foreach ($encryptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('smtp_encryption', $options['smtp_encryption']) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('smtp_encryption')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="smtp_username">Utilizador SMTP</label>
            <div class="col-lg-5">
                <input id="smtp_username" name="smtp_username" type="text" class="form-control @error('smtp_username') is-invalid @enderror" value="{{ old('smtp_username', $options['smtp_username']) }}" autocomplete="username">
                @error('smtp_username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="smtp_password">Password SMTP</label>
            <div class="col-lg-5">
                <input id="smtp_password" name="smtp_password" type="password" class="form-control @error('smtp_password') is-invalid @enderror" value="" autocomplete="new-password" placeholder="{{ empty($options['smtp_password']) ? '' : 'Password configurada' }}">
                <div class="form-text">Deixe em branco para manter a password atual.</div>
                @error('smtp_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="smtp_from_address">Email do remetente</label>
            <div class="col-lg-5">
                <input id="smtp_from_address" name="smtp_from_address" type="email" class="form-control @error('smtp_from_address') is-invalid @enderror" value="{{ old('smtp_from_address', $options['smtp_from_address']) }}">
                @error('smtp_from_address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="smtp_from_name">Nome do remetente</label>
            <div class="col-lg-5">
                <input id="smtp_from_name" name="smtp_from_name" type="text" class="form-control @error('smtp_from_name') is-invalid @enderror" value="{{ old('smtp_from_name', $options['smtp_from_name']) }}">
                @error('smtp_from_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row">
            <div class="offset-lg-2 col-lg-6">
                <button class="btn btn-primary" type="submit">Guardar configuração</button>
            </div>
        </div>
    </form>

    <form class="bg-white border rounded-2 p-4 cms-settings-form" method="POST" action="{{ route('admin.smtp-settings.test') }}">
        @csrf

        <div class="row g-3 align-items-start">
            <label class="col-lg-2 col-form-label fw-semibold" for="test_recipient">Email de teste</label>
            <div class="col-lg-5">
                <input id="test_recipient" name="test_recipient" type="email" class="form-control @error('test_recipient') is-invalid @enderror" value="{{ old('test_recipient', $options['admin_email']) }}" required>
                <div class="form-text">Guarde a configuração antes de testar.</div>
                @error('test_recipient')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-lg-3">
                <button class="btn btn-outline-primary" type="submit">Enviar teste</button>
            </div>
        </div>
    </form>
@endsection
