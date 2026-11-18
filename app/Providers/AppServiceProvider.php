<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config()->set('cms-core.updates.starter.version', '2.1.0');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once base_path('bootstrap/plugin-catalog.php');
        \Pcteckserv\Distribution\PluginCatalogDistribution::boot($this->app);
    }
}
