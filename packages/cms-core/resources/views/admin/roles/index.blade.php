@extends('cms-core::admin.layouts.app', ['title' => 'Roles'])

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Roles</h1>
        @can('core.roles.create')
            <a class="btn btn-primary" href="{{ route('admin.roles.create') }}">Criar role</a>
        @endcan
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="table-responsive bg-white border rounded">
        <table class="table align-middle mb-0">
            <thead><tr><th>Nome</th><th>Chave</th><th>Permissões</th><th>Protegida</th><th></th></tr></thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr>
                        <td>{{ $role->name }}</td>
                        <td><code>{{ $role->key }}</code></td>
                        <td>{{ $role->permissions_count }}</td>
                        <td>{{ $role->is_protected ? 'Sim' : 'Não' }}</td>
                        <td class="text-end">
                            @can('core.roles.update')
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.roles.edit', $role) }}">Editar</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $roles->links() }}</div>
@endsection
