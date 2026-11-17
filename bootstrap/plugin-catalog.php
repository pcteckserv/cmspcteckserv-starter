<?php

namespace Pcteckserv\Distribution;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;
use Pcteckserv\CmsCore\Plugins\DTOs\AvailablePlugin;
use Pcteckserv\CmsCore\Plugins\PluginRepository;

/** Distribuído nos sites, usando os pontos de extensão do Core. */
final class PluginCatalogDistribution extends PluginRepository
{
    public const LABELS = ['customers' => 'Clientes', 'shop' => 'Loja', 'product-customization' => 'Personalização'];

    private const DIRECTORIES = [
        'pcteckserv/cms-customers' => 'cmspcteckserv-clientes',
        'pcteckserv/cms-shop' => 'cmspcteckserv-loja',
        'pcteckserv/cms-product-customization' => 'cmspcteckserv-personalizacao',
    ];

    private const RENAMED_VERSIONS = [
        'pcteckserv/cms-customers' => '1.5.1',
        'pcteckserv/cms-shop' => '0.52.2',
        'pcteckserv/cms-product-customization' => '0.3.1',
    ];

    public static function obsolete(string $slug, string $package): bool
    {
        return in_array($slug, ['customer-integrations', 'ecommerce', 'e-commerce'], true)
            || in_array($package, ['pcteckserv/cms-customer-integrations', 'pcteckserv/cms-ecommerce', 'pcteckserv/cms-e-commerce'], true);
    }

    public function available(): Collection
    {
        $root = config('cms-plugins.local_repository');
        // Algumas distribuições antigas do Core ignoram local_repository.
        $plugins = is_string($root) && is_dir($root) ? $this->local($root) : parent::available();

        return $plugins->filter(function (AvailablePlugin $plugin): bool {
            if (self::obsolete($plugin->slug, $plugin->package)) {
                return false;
            }
            $canonical = self::DIRECTORIES[$plugin->package] ?? null;

            return $canonical === null || strtolower($plugin->directory) === $canonical.'-bydl'
                || (strtolower($plugin->directory) === $canonical && version_compare($plugin->version, self::RENAMED_VERSIONS[$plugin->package], '>='));
        })->sortBy(fn (AvailablePlugin $plugin) => strtolower($plugin->directory) === (self::DIRECTORIES[$plugin->package] ?? '') ? 0 : 1)->map(function (AvailablePlugin $plugin): AvailablePlugin {
            $data = get_object_vars($plugin);
            $data['label'] = self::LABELS[$plugin->slug] ?? $plugin->label;

            return new AvailablePlugin(...$data);
        })->unique('package')->sortBy('label')->values();
    }

    private function local(string $root): Collection
    {
        return collect(File::directories($root))->map(function (string $directory): ?AvailablePlugin {
            $file = $directory.'/cms-plugin.json';
            if (! is_file($file)) return null;
            $data = json_decode(file_get_contents($file), true);
            if (! is_array($data)
                || ! is_string($data['package'] ?? null) || ! preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $data['package'])
                || ! is_string($data['slug'] ?? null) || ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $data['slug'])
                || ! is_string($data['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $data['version'])) return null;
            $arguments = ['slug' => $data['slug'], 'directory' => basename($directory), 'package' => $data['package'],
                'label' => is_string($data['label'] ?? null) ? $data['label'] : $data['slug'],
                'description' => is_string($data['description'] ?? null) ? $data['description'] : null,
                'provider' => is_string($data['provider'] ?? null) ? $data['provider'] : null,
                'versionConstraint' => is_string($data['version_constraint'] ?? null) ? $data['version_constraint'] : null,
                'repositoryPath' => $directory, 'version' => $data['version']];
            if (property_exists(AvailablePlugin::class, 'coreConstraint')) {
                $composer = is_file($directory.'/composer.json') ? json_decode(file_get_contents($directory.'/composer.json'), true) : [];
                $constraint = $composer['require']['pcteckserv/cms-core'] ?? null;
                $arguments['coreConstraint'] = is_string($constraint) ? $constraint : null;
            }

            return new AvailablePlugin(...$arguments);
        })->filter();
    }

    public static function boot($app): void
    {
        // Ponte partilhada para Starters em Cores já instalados. Respeita uma
        // integração explícita do próprio site, como a da CG Limpezas.
        if (! $app->bound(\Pcteckserv\CmsCore\Updates\StarterUpdater::class)) {
            $bridge = __DIR__.'/CompatibleStarterUpdater.php';
            if (!is_file($bridge)) $bridge = base_path('bootstrap/CompatibleStarterUpdater.php');
            if (is_file($bridge)) {
                require_once $bridge;
                $app->bind(\Pcteckserv\CmsCore\Updates\StarterUpdater::class, CompatibleStarterUpdater::class);
            }
        }
        $app->bind(PluginRepository::class, self::class);
        foreach (self::LABELS as $slug => $label) {
            if (config('cms-plugins.plugins.'.$slug)) {
                config()->set('cms-plugins.plugins.'.$slug.'.label', $label);
            }
        }
        View::composer('cms-core::admin.plugins.index', function ($view): void {
            // Preserva instalações antigas reais para poderem ser desinstaladas no painel.
            $plugins = $view->getData()['plugins'] ?? collect();
            $view->with('plugins', $plugins->filter(fn ($plugin) => $plugin->installed_version !== null
                || ! self::obsolete($plugin->slug, $plugin->package ?? ''))->values());
        });
    }
}
