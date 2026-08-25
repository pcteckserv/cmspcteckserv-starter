<?php

namespace Pcteckserv\CmsCore;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pcteckserv\CmsCore\Console\CheckUpdatesCommand;
use Pcteckserv\CmsCore\Console\SyncPermissionsCommand;
use Pcteckserv\CmsCore\Console\SyncVersionsCommand;
use Pcteckserv\CmsCore\Contracts\CmsAccessUser;
use Pcteckserv\CmsCore\Support\Permissions\PermissionRegistry;
use Pcteckserv\CmsCore\Services\UserModelResolver;
use Pcteckserv\CmsCore\Support\SiteOptions;

class CmsCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cms-core.php', 'cms-core');
        $this->app->singleton(PermissionRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cms-core');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/cms-core.php' => config_path('cms-core.php'),
        ], 'cms-core-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/cms-core'),
        ], 'cms-core-views');

        $this->publishes([
            __DIR__.'/../resources/images' => public_path('vendor/cms-core/images'),
        ], 'cms-core-assets');

        $this->app->make(SiteOptions::class)->applyMailConfig();
        Route::bind('user', fn ($value) => app(UserModelResolver::class)->className()::query()->findOrFail($value));
        $this->registerCorePermissions();
        $this->registerGates();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckUpdatesCommand::class,
                SyncPermissionsCommand::class,
                SyncVersionsCommand::class,
            ]);
        }
    }

    private function registerCorePermissions(): void
    {
        $this->app->make(PermissionRegistry::class)->register([
            'core.users.view' => ['label' => 'Ver utilizadores', 'group' => 'Utilizadores'],
            'core.users.create' => ['label' => 'Criar utilizadores', 'group' => 'Utilizadores'],
            'core.users.update' => ['label' => 'Editar utilizadores', 'group' => 'Utilizadores'],
            'core.users.delete' => ['label' => 'Eliminar utilizadores', 'group' => 'Utilizadores'],
            'core.users.manage_roles' => ['label' => 'Gerir roles de utilizadores', 'group' => 'Utilizadores'],
            'core.roles.view' => ['label' => 'Ver roles', 'group' => 'Roles'],
            'core.roles.create' => ['label' => 'Criar roles', 'group' => 'Roles'],
            'core.roles.update' => ['label' => 'Editar roles', 'group' => 'Roles'],
            'core.roles.delete' => ['label' => 'Eliminar roles', 'group' => 'Roles'],
        ]);
    }

    private function registerGates(): void
    {
        Gate::before(function ($user, string $ability): ?bool {
            if ($user instanceof CmsAccessUser && $user->isCmsSuperAdmin()) {
                return true;
            }

            if ($user instanceof CmsAccessUser && $this->app->make(PermissionRegistry::class)->has($ability)) {
                return $user->hasCmsPermission($ability);
            }

            return null;
        });

        foreach ($this->app->make(PermissionRegistry::class)->all() as $permission) {
            Gate::define(
                $permission->key,
                fn ($user): bool => $user instanceof CmsAccessUser && $user->hasCmsPermission($permission->key),
            );
        }
    }
}
