@extends('cms-core::admin.layouts.app', ['title' => 'Utilizadores'])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Utilizadores</h1>
        @can('core.users.create')
            <a class="btn btn-primary" href="{{ route('admin.users.create') }}">Criar utilizador</a>
        @endcan
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-5"><input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Pesquisar por nome ou email"></div>
        <div class="col-md-3">
            <select class="form-select" name="state">
                <option value="">Todos os estados</option>
                <option value="active" @selected(($filters['state'] ?? '') === 'active')>Ativo</option>
                <option value="inactive" @selected(($filters['state'] ?? '') === 'inactive')>Inativo</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="role">
                <option value="">Todas as roles</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) ($filters['role'] ?? '') === (string) $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-outline-primary w-100" type="submit">Filtrar</button></div>
    </form>

    <div class="table-responsive bg-white border rounded">
        <table class="table align-middle mb-0">
            <thead><tr><th>Nome</th><th>Email</th><th>Roles</th><th>Estado</th><th>Criado em</th><th>Último acesso</th><th></th></tr></thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->cmsRoles->pluck('name')->join(', ') ?: 'Sem role' }}</td>
                        <td>{{ $user->cmsAccessState() === 'active' ? 'Ativo' : 'Inativo' }}</td>
                        <td>{{ $user->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $user->cmsState?->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</td>
                        <td class="text-end">
                            @can('core.users.update')
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $user) }}">Editar</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $users->links() }}</div>
@endsection
