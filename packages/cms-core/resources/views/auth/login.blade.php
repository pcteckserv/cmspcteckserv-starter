@extends('cms-core::admin.layouts.guest', ['title' => 'Iniciar sessão'])

@section('content')
    <main class="min-vh-100 d-flex align-items-center py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-5">
                    <div class="bg-white border rounded-2 shadow-sm p-4">
                        <h1 class="h3 mb-1">Iniciar sessão</h1>
                        <p class="text-secondary mb-4">Aceda ao painel administrativo.</p>

                        <form method="POST" action="{{ route('login.store') }}" novalidate>
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="email">Email</label>
                                <input
                                    class="form-control @error('email') is-invalid @enderror"
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    autocomplete="email"
                                    required
                                    autofocus
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="password">Palavra-passe</label>
                                <input
                                    class="form-control @error('password') is-invalid @enderror"
                                    id="password"
                                    name="password"
                                    type="password"
                                    autocomplete="current-password"
                                    required
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                                <label class="form-check-label" for="remember">Manter sessão iniciada</label>
                            </div>

                            <button class="btn btn-primary w-100" type="submit">Entrar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
