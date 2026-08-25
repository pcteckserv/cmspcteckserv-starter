@extends('cms-core::admin.layouts.app', ['title' => 'Opções gerais'])

@section('content')
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Opções gerais</h1>
            <p class="text-secondary mb-0">Parâmetros globais usados como fallback nos sites dos clientes.</p>
        </div>
    </div>

    @if (session('cms_site_options_success'))
        <div class="alert alert-success">{{ session('cms_site_options_success') }}</div>
    @endif

    <form class="bg-white border rounded-2 p-4 cms-settings-form" method="POST" action="{{ route('admin.site-options.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="site_title">Título do site</label>
            <div class="col-lg-5">
                <input id="site_title" name="site_title" type="text" class="form-control @error('site_title') is-invalid @enderror" value="{{ old('site_title', $options['site_title']) }}" required>
                @error('site_title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="site_description">Descrição</label>
            <div class="col-lg-6">
                <input id="site_description" name="site_description" type="text" class="form-control @error('site_description') is-invalid @enderror" value="{{ old('site_description', $options['site_description']) }}">
                <div class="form-text">Em poucas palavras, explique do que trata este site.</div>
                @error('site_description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="site_icon_url">Ícone do site</label>
            <div class="col-lg-6">
                <input id="site_icon_file" name="site_icon_file" type="file" class="form-control mb-2 @error('site_icon_file') is-invalid @enderror" accept=".png,.jpg,.jpeg,.webp,.ico">
                @error('site_icon_file')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="input-group">
                    <input id="site_icon_url" name="site_icon_url" type="text" class="form-control @error('site_icon_url') is-invalid @enderror" value="{{ old('site_icon_url', $options['site_icon_url']) }}" placeholder="/favicon.ico">
                    <button class="btn btn-outline-danger" type="submit" name="remove_site_icon" value="1">Remover</button>
                </div>
                <div class="form-text">Escolha um ficheiro ou indique uma URL. O ícone deve ser quadrado e ter pelo menos 512 por 512 píxeis.</div>
                @error('site_icon_url')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="site_url">Endereço do site (URL)</label>
            <div class="col-lg-5">
                <input id="site_url" name="site_url" type="url" class="form-control @error('site_url') is-invalid @enderror" value="{{ old('site_url', $options['site_url']) }}" required>
                <div class="form-text">Digite aqui o endereço público principal do site do cliente.</div>
                @error('site_url')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="admin_email">Endereço de email de administração</label>
            <div class="col-lg-5">
                <input id="admin_email" name="admin_email" type="email" class="form-control @error('admin_email') is-invalid @enderror" value="{{ old('admin_email', $options['admin_email']) }}" required>
                <div class="form-text">Este endereço é utilizado para fins administrativos.</div>
                @error('admin_email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row g-3 align-items-start mb-4">
            <label class="col-lg-2 col-form-label fw-semibold" for="locale">Idioma do site</label>
            <div class="col-lg-4">
                <select id="locale" name="locale" class="form-select @error('locale') is-invalid @enderror" required>
                    @foreach ($locales as $value => $label)
                        <option value="{{ $value }}" @selected(old('locale', $options['locale']) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('locale')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="row">
            <div class="offset-lg-2 col-lg-6">
                <button class="btn btn-primary" type="submit">Guardar alterações</button>
            </div>
        </div>
    </form>
@endsection
