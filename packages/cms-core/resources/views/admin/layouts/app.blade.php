<!doctype html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Administração' }}</title>
    <link rel="icon" href="{{ app(\Pcteckserv\CmsCore\Support\SiteOptions::class)->get('site_icon_url') }}">
    @vite([
        'vendor/pcteckserv/cms-core/resources/css/admin.scss',
        'vendor/pcteckserv/cms-core/resources/js/admin.js',
    ])
</head>
<body>
    <div class="admin-shell d-md-flex bg-light">
        <aside class="admin-sidebar bg-dark text-white p-3">
            <div class="fw-semibold mb-4">CMS PCTECK</div>
            <nav class="nav nav-pills flex-column">
                <a @class(['nav-link', 'active' => request()->routeIs('admin.dashboard')]) href="{{ route('admin.dashboard') }}">Dashboard</a>
                @can('core.users.view')
                    <a @class(['nav-link', 'active' => request()->routeIs('admin.users.*')]) href="{{ route('admin.users.index') }}">Utilizadores</a>
                @endcan
                @can('core.roles.view')
                    <a @class(['nav-link', 'active' => request()->routeIs('admin.roles.*')]) href="{{ route('admin.roles.index') }}">Roles</a>
                @endcan
                <a @class(['nav-link', 'active' => request()->routeIs('admin.site-options.*')]) href="{{ route('admin.site-options.edit') }}">Opções gerais</a>
                <a @class(['nav-link', 'active' => request()->routeIs('admin.smtp-settings.*')]) href="{{ route('admin.smtp-settings.edit') }}">SMTP</a>
                <a @class(['nav-link', 'active' => request()->routeIs('admin.laravel-commands.*')]) href="{{ route('admin.laravel-commands.index') }}">Comandos Laravel</a>
                <a @class(['nav-link', 'active' => request()->routeIs('admin.updates.*')]) href="{{ route('admin.updates.index') }}">Atualizações</a>
            </nav>
        </aside>

        <div class="flex-grow-1">
            <header class="bg-white border-bottom">
                <div class="container-fluid py-3 d-flex justify-content-between align-items-center gap-3">
                    <div>
                        <span class="text-secondary small">Utilizador</span>
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Terminar sessão</button>
                    </form>
                </div>
            </header>

            <main class="container-fluid py-4">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
