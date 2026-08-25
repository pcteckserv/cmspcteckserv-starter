<?php

namespace Pcteckserv\CmsCore\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Pcteckserv\CmsCore\Http\Requests\Admin\StoreUserRequest;
use Pcteckserv\CmsCore\Http\Requests\Admin\UpdateUserRequest;
use Pcteckserv\CmsCore\Models\Permission;
use Pcteckserv\CmsCore\Models\Role;
use Pcteckserv\CmsCore\Services\UserModelResolver;

class UsersController extends Controller
{
    public function __construct(private readonly UserModelResolver $users)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('core.users.view');

        $userModel = $this->users->className();
        $query = $userModel::query()->with(['cmsRoles', 'cmsState']);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($state = $request->query('state')) {
            $query->whereHas('cmsState', fn ($query) => $query->where('state', $state));
        }

        if ($role = $request->query('role')) {
            $query->whereHas('cmsRoles', fn ($query) => $query->where('cms_roles.id', $role));
        }

        return view('cms-core::admin.users.index', [
            'users' => $query->latest()->paginate((int) config('cms-core.users_per_page', 15))->withQueryString(),
            'roles' => Role::query()->orderBy('name')->get(),
            'filters' => $request->only(['search', 'state', 'role']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('core.users.create');

        return view('cms-core::admin.users.create', $this->formData());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $userModel = $this->users->className();
        $data = $request->validated();

        DB::transaction(function () use ($userModel, $data, $request): void {
            $user = $userModel::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->cmsState()->create(['state' => $data['state']]);
            $user->cmsRoles()->sync($request->roleIds());
            $user->cmsPermissions()->sync($request->permissionIds());
        });

        return redirect()->route('admin.users.index')->with('status', 'Utilizador criado com sucesso.');
    }

    public function edit(mixed $user): View
    {
        Gate::authorize('core.users.update');

        return view('cms-core::admin.users.edit', array_merge($this->formData(), [
            'user' => $user->load(['cmsRoles', 'cmsPermissions', 'cmsState']),
        ]));
    }

    public function update(UpdateUserRequest $request, mixed $user): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request, $user): void {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (! empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }

            $user->update($payload);
            $user->cmsState()->updateOrCreate([], ['state' => $data['state']]);
            $user->cmsRoles()->sync($request->roleIds());
            $user->cmsPermissions()->sync($request->permissionIds());
        });

        return redirect()->route('admin.users.edit', $user)->with('status', 'Utilizador atualizado com sucesso.');
    }

    public function destroy(mixed $user): RedirectResponse
    {
        Gate::authorize('core.users.delete');

        if (method_exists($user, 'isCmsSuperAdmin') && $user->isCmsSuperAdmin()) {
            return back()->withErrors(['user' => 'Não é possível eliminar um Super Admin.']);
        }

        $user->cmsState()->updateOrCreate([], ['state' => 'inactive']);

        return redirect()->route('admin.users.index')->with('status', 'Utilizador desativado com sucesso.');
    }

    private function formData(): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->get(),
            'permissionsByGroup' => Permission::query()->orderBy('group')->orderBy('label')->get()->groupBy('group'),
            'states' => config('cms-core.user_states', ['active', 'inactive']),
        ];
    }
}
