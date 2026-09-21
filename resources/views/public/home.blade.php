@extends('layouts.app', ['title' => 'CMS PCTECKSERV'])

@section('content')
    <nav class="site-navbar navbar navbar-expand-lg" aria-label="Navegação principal">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}" aria-label="PCTECKSERV — Página inicial">
                <img class="site-logo" src="{{ asset('images/logotipos-pcteckserv-texto.svg') }}" alt="PCTECKSERV">
            </a>
            <a class="btn btn-outline-dark btn-sm px-3" href="{{ route('login') }}">Área administrativa</a>
        </div>
    </nav>

    <main>
        <section class="public-hero">
            <div class="container position-relative">
                <div class="row align-items-center g-5">
                    <div class="col-lg-7" data-gsap="fade-in">
                        <p class="section-kicker mb-3">CMS PCTECKSERV</p>
                        <h1 class="display-4 fw-bold mb-4">Conteúdos bem organizados. Websites preparados para crescer.</h1>
                        <p class="hero-copy mb-4">
                            Uma base de gestão simples e segura para criar, atualizar e publicar conteúdos,
                            mantendo cada website consistente e fácil de administrar.
                        </p>
                        <a class="btn btn-brand btn-lg" href="{{ route('login') }}">Entrar no painel</a>
                    </div>

                    <div class="col-lg-5" data-gsap="fade-in">
                        <div class="cms-summary">
                            <span class="summary-number" aria-hidden="true">CMS</span>
                            <h2 class="h4 mb-3">O que é um CMS?</h2>
                            <p class="mb-0">
                                É um sistema de gestão de conteúdos. Permite à sua equipa atualizar páginas,
                                textos, imagens e menus num único painel, sem ter de alterar código sempre que
                                existe uma novidade.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="benefits-section" aria-labelledby="benefits-title">
            <div class="container">
                <div class="section-heading" data-gsap="fade-in">
                    <p class="section-kicker mb-2">Uma base sólida</p>
                    <h2 id="benefits-title" class="h1 mb-3">Mais autonomia, menos complexidade.</h2>
                    <p class="text-secondary mb-0">
                        Tudo o que é essencial para gerir o conteúdo diário e acompanhar a evolução do website.
                    </p>
                </div>

                <div class="row g-4 mt-2">
                    <div class="col-md-4" data-gsap="fade-in">
                        <article class="benefit-item h-100">
                            <span class="benefit-index">01</span>
                            <h3 class="h5">Gestão centralizada</h3>
                            <p class="mb-0">Páginas, conteúdos e configurações reunidos num painel claro e acessível.</p>
                        </article>
                    </div>
                    <div class="col-md-4" data-gsap="fade-in">
                        <article class="benefit-item h-100">
                            <span class="benefit-index">02</span>
                            <h3 class="h5">Edição com autonomia</h3>
                            <p class="mb-0">Atualizações mais rápidas, sem depender de desenvolvimento para cada alteração.</p>
                        </article>
                    </div>
                    <div class="col-md-4" data-gsap="fade-in">
                        <article class="benefit-item h-100">
                            <span class="benefit-index">03</span>
                            <h3 class="h5">Preparado para evoluir</h3>
                            <p class="mb-0">Uma estrutura modular e segura que acompanha novos conteúdos e funcionalidades.</p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="closing-section">
            <div class="container d-lg-flex align-items-center justify-content-between gap-4" data-gsap="fade-in">
                <div>
                    <p class="section-kicker mb-2">Área reservada</p>
                    <h2 class="h3 mb-2">Pronto para gerir o seu website?</h2>
                    <p class="text-secondary mb-0">Aceda ao painel para consultar e atualizar os conteúdos disponíveis.</p>
                </div>
                <a class="btn btn-dark mt-4 mt-lg-0 flex-shrink-0" href="{{ route('login') }}">Aceder ao CMS</a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="container d-sm-flex align-items-center justify-content-between gap-3">
            <span>&copy; {{ date('Y') }} PCTECKSERV. Todos os direitos reservados.</span>
            <span>Gestão de conteúdos simples e segura.</span>
        </div>
    </footer>
@endsection
