<?php

namespace Pcteckserv\CmsCore\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Pcteckserv\CmsCore\Models\Role;
use Pcteckserv\CmsCore\Services\UserModelResolver;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.users.create') ?? false;
    }

    public function rules(UserModelResolver $resolver): array
    {
        $userModel = $resolver->className();
        $table = (new $userModel)->getTable();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique($table, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'state' => ['required', Rule::in(config('cms-core.user_states', ['active', 'inactive']))],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('cms_roles', 'id')],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('cms_permissions', 'id')],
        ];
    }

    public function roleIds(): array
    {
        $roleIds = array_map('intval', $this->validated('roles', []));

        if (! $this->user()?->can('core.users.manage_roles')) {
            return [];
        }

        if ($this->user()?->isCmsSuperAdmin()) {
            return $roleIds;
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->where('is_protected', false)
            ->pluck('id')
            ->all();
    }

    public function permissionIds(): array
    {
        if (! $this->user()?->can('core.users.manage_roles')) {
            return [];
        }

        return array_map('intval', $this->validated('permissions', []));
    }
}
