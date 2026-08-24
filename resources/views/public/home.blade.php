@extends('layouts.app', ['title' => 'Página inicial'])

@section('content')
    <nav class="navbar navbar-expand-lg bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ route('home') }}">CMS PCTECK</a>
            <div class="ms-auto">
                <a class="btn btn-outline-primary btn-sm" href="{{ route('login') }}">Área administrativa</a>
            </div>
        </div>
    </nav>

    <main>
        <section class="public-hero d-flex align-items-center">
            <div class="container py-5">
                <div class="row align-items-center">
                    <div class="col-lg-8" data-gsap="fade-in">
                        <p class="text-uppercase text-primary fw-semibold mb-2">Base inicial</p>
                        <h1 class="display-5 fw-bold mb-3">CMS preparado para crescer com os websites da empresa.</h1>
                        <p class="lead text-secondary mb-4">
                            Estrutura pública simples, Bootstrap configurado e integração inicial de animações com GSAP.
                        </p>
                        <a class="btn btn-primary" href="{{ route('login') }}">Entrar no painel</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white border-top border-bottom py-5">
            <div class="container" data-gsap="fade-in">
                <div class="row g-4">
                    <div class="col-md-4">
                        <h2 class="h5">Estrutura limpa</h2>
                        <p class="text-secondary mb-0">Layouts Blade separados para a área pública e administrativa.</p>
                    </div>
                    <div class="col-md-4">
                        <h2 class="h5">Frontend reutilizável</h2>
                        <p class="text-secondary mb-0">Assets compilados por Vite, com Bootstrap e SCSS preparados.</p>
                    </div>
                    <div class="col-md-4">
                        <h2 class="h5">Base segura</h2>
                        <p class="text-secondary mb-0">Painel administrativo protegido por autenticação Laravel.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="py-4">
        <div class="container text-secondary small">
            &copy; {{ date('Y') }} CMS PCTECK. Todos os direitos reservados.
        </div>
    </footer>
@endsection
