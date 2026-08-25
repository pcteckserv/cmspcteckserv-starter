<?php

namespace Pcteckserv\CmsCore\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Pcteckserv\CmsCore\Http\Requests\Admin\StoreRoleRequest;
use Pcteckserv\CmsCore\Http\Requests\Admin\UpdateRoleRequest;
use Pcteckserv\CmsCore\Models\Permission;
use Pcteckserv\CmsCore\Models\Role;

class RolesController
{
    public function index(): View
    {
        Gate::authorize('core.roles.view');

        return view('cms-core::admin.roles.index', [
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('core.roles.create');

        return view('cms-core::admin.roles.create', $this->formData());
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create($request->safe()->only(['name', 'key']));
        $role->permissions()->sync($request->validated('permissions', []));

        return redirect()->route('admin.roles.edit', $role)->with('status', 'Role criada com sucesso.');
    }

    public function edit(Role $role): View
    {
        Gate::authorize('core.roles.update');

        return view('cms-core::admin.roles.edit', array_merge($this->formData(), [
            'role' => $role->load('permissions'),
        ]));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->is_protected && ! $request->user()?->isCmsSuperAdmin()) {
            abort(403);
        }

        $role->update($request->safe()->only(['name', 'key']));
        $role->permissions()->sync($request->validated('permissions', []));

        return redirect()->route('admin.roles.edit', $role)->with('status', 'Role atualizada com sucesso.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('core.roles.delete');

        if ($role->is_protected) {
            return back()->withErrors(['role' => 'Não é possível eliminar uma role protegida.']);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Role eliminada com sucesso.');
    }

    private function formData(): array
    {
        return [
            'permissionsByGroup' => Permission::query()->orderBy('group')->orderBy('label')->get()->groupBy('group'),
        ];
    }
}
