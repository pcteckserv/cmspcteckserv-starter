@if ($errors->any())
    <div class="alert alert-danger">Corrija os campos assinalados.</div>
@endif

<form method="POST" action="{{ $action }}" class="bg-white border rounded p-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="name">Nome</label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user?->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="email">Email</label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user?->email) }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password">Palavra-passe</label>
            <input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" @if (! $user) required @endif>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="password_confirmation">Confirmar palavra-passe</label>
            <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" @if (! $user) required @endif>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="state">Estado</label>
            <select class="form-select" id="state" name="state">
                @foreach ($states as $state)
                    <option value="{{ $state }}" @selected(old('state', $user?->cmsAccessState() ?? 'active') === $state)>{{ $state === 'active' ? 'Ativo' : 'Inativo' }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @can('core.users.manage_roles')
        <hr>
        <h2 class="h5">Roles</h2>
        @foreach ($roles as $role)
            <div class="form-check">
                <input class="form-check-input" id="role-{{ $role->id }}" type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $user?->cmsRoles->pluck('id')->all() ?? [])))>
                <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
            </div>
        @endforeach

        <hr>
        <h2 class="h5">Permissões diretas</h2>
        @foreach ($permissionsByGroup as $group => $permissions)
            <div class="mb-3">
                <div class="fw-semibold">{{ $group }}</div>
                @foreach ($permissions as $permission)
                    <div class="form-check">
                        <input class="form-check-input" id="permission-{{ $permission->id }}" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permissions', $user?->cmsPermissions->pluck('id')->all() ?? [])))>
                        <label class="form-check-label" for="permission-{{ $permission->id }}">{{ $permission->label }}</label>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endcan

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}">Cancelar</a>
    </div>
</form>
