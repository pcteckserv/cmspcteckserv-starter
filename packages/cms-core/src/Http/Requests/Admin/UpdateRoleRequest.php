<?php

namespace Pcteckserv\CmsCore\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.roles.update') ?? false;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:\.[a-z0-9_-]+)+$/', Rule::unique('cms_roles', 'key')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('cms_permissions', 'id')],
        ];
    }
}
