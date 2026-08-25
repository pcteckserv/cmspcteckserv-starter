<?php

namespace Pcteckserv\CmsCore\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Pcteckserv\CmsCore\Models\Permission;
use Pcteckserv\CmsCore\Models\Role;
use Pcteckserv\CmsCore\Models\UserState;

trait HasCmsAccess
{
    public function cmsRoles(): BelongsToMany
    {
        return $this->morphToMany(Role::class, 'user', 'cms_role_user');
    }

    public function cmsPermissions(): BelongsToMany
    {
        return $this->morphToMany(Permission::class, 'user', 'cms_permission_user');
    }

    public function cmsState(): MorphOne
    {
        return $this->morphOne(UserState::class, 'user');
    }

    public function hasCmsRole(string $role): bool
    {
        $roles = $this->relationLoaded('cmsRoles') ? $this->cmsRoles : $this->cmsRoles()->get(['key']);

        return $roles->contains('key', $role);
    }

    public function hasCmsPermission(string $permission): bool
    {
        if ($this->isCmsSuperAdmin()) {
            return true;
        }

        if ($this->cmsPermissions()->where('key', $permission)->exists()) {
            return true;
        }

        return $this->cmsRoles()
            ->whereHas('permissions', fn ($query) => $query->where('key', $permission))
            ->exists();
    }

    public function isCmsSuperAdmin(): bool
    {
        return $this->hasCmsRole(config('cms-core.super_admin_role', 'core.super_admin'));
    }

    public function cmsAccessState(): string
    {
        return $this->cmsState?->state ?? 'active';
    }

    public function isCmsActive(): bool
    {
        return $this->cmsAccessState() === 'active';
    }
}
