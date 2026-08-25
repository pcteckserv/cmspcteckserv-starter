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
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $role?->name) }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="key">Chave</label>
            <input class="form-control @error('key') is-invalid @enderror" id="key" name="key" value="{{ old('key', $role?->key) }}" required @readonly($role?->is_protected)>
            @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <hr>
    <h2 class="h5">Permissões</h2>
    @foreach ($permissionsByGroup as $group => $permissions)
        <div class="mb-3">
            <div class="fw-semibold">{{ $group }}</div>
            @foreach ($permissions as $permission)
                <div class="form-check">
                    <input class="form-check-input" id="permission-{{ $permission->id }}" type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permissions', $role?->permissions->pluck('id')->all() ?? [])))>
                    <label class="form-check-label" for="permission-{{ $permission->id }}">{{ $permission->label }}</label>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.roles.index') }}">Cancelar</a>
    </div>
</form>
