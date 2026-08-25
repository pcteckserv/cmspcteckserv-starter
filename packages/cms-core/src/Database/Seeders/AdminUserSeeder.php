<?php

namespace Pcteckserv\CmsCore\Database\Seeders;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Pcteckserv\CmsCore\Models\Permission;
use Pcteckserv\CmsCore\Models\Role;
use Pcteckserv\CmsCore\Services\PermissionSynchronizer;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $userModel = $this->userModel();
        $email = config('cms-core.admin_user.email');
        $password = config('cms-core.admin_user.password');

        app(PermissionSynchronizer::class)->sync();

        if (! is_string($email) || trim($email) === '') {
            throw new InvalidArgumentException('Configure ADMIN_USER_EMAIL antes de criar o administrador inicial.');
        }

        if (! is_string($password) || trim($password) === '') {
            throw new InvalidArgumentException('Configure ADMIN_USER_PASSWORD antes de criar o administrador inicial.');
        }

        $user = $userModel::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('cms-core.admin_user.name'),
                'password' => Hash::make($password),
            ],
        );

        $role = Role::query()->firstOrCreate(
            ['key' => config('cms-core.super_admin_role', 'core.super_admin')],
            ['name' => 'Super Admin', 'is_protected' => true],
        );

        $this->createDefaultRoles();

        if (method_exists($user, 'cmsRoles')) {
            $user->cmsRoles()->syncWithoutDetaching([$role->id]);
        }

        if (method_exists($user, 'cmsState')) {
            $user->cmsState()->updateOrCreate([], ['state' => 'active']);
        }
    }

    private function createDefaultRoles(): void
    {
        $admin = Role::query()->firstOrCreate(
            ['key' => 'core.admin'],
            ['name' => 'Administrador', 'is_protected' => false],
        );

        $editor = Role::query()->firstOrCreate(
            ['key' => 'core.editor'],
            ['name' => 'Editor', 'is_protected' => false],
        );

        $admin->permissions()->syncWithoutDetaching(
            Permission::query()->where('key', '!=', 'core.users.manage_roles')->pluck('id')->all(),
        );

        $editor->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('key', ['core.users.view'])->pluck('id')->all(),
        );
    }

    /**
     * @return class-string<Authenticatable>
     */
    private function userModel(): string
    {
        $userModel = config('cms-core.user_model') ?: config('auth.providers.users.model');

        if (! is_string($userModel) || ! class_exists($userModel)) {
            throw new InvalidArgumentException('Configure um model de utilizador válido para o CMS Core.');
        }

        if (! is_subclass_of($userModel, Authenticatable::class)) {
            throw new InvalidArgumentException('O model de utilizador do CMS Core deve implementar Authenticatable.');
        }

        return $userModel;
    }
}
