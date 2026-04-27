<?php

/**
 * Locations Service Provider
 *
 * @see Platform\Core\PlatformCore für Modul-Registrierung
 * @see Platform\Core\Routing\ModuleRouter für Route-Registrierung
 */

namespace Platform\Locations;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LocationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/locations.php', 'locations');
    }

    public function boot(): void
    {
        if (
            config()->has('locations.routing') &&
            config()->has('locations.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'locations',
                'title'      => 'Locations',
                'group'      => 'operations',
                'routing'    => config('locations.routing'),
                'guard'      => config('locations.guard'),
                'navigation' => config('locations.navigation'),
                'sidebar'    => config('locations.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('locations')) {
            ModuleRouter::group('locations', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/locations.php' => config_path('locations.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'locations');

        $this->registerLivewireComponents();

        // Tools registrieren (loose gekoppelt - für AI/Chat)
        $this->registerTools();
    }

    /**
     * Registriert Locations-Tools für die AI/Chat-Funktionalität.
     */
    protected function registerTools(): void
    {
        try {
            if (!class_exists(\Platform\Core\Tools\ToolRegistry::class)) {
                return;
            }

            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            $registry->register(new \Platform\Locations\Tools\ListLocationsTool());
            $registry->register(new \Platform\Locations\Tools\GetLocationTool());
            $registry->register(new \Platform\Locations\Tools\CreateLocationTool());
            $registry->register(new \Platform\Locations\Tools\UpdateLocationTool());
            $registry->register(new \Platform\Locations\Tools\DeleteLocationTool());

            // Bulk-Tools
            $registry->register(new \Platform\Locations\Tools\BulkCreateLocationsTool());
            $registry->register(new \Platform\Locations\Tools\BulkUpdateLocationsTool());
            $registry->register(new \Platform\Locations\Tools\BulkDeleteLocationsTool());

            // Pricing-Sub-Entity
            $registry->register(new \Platform\Locations\Tools\ListLocationPricingsTool());
            $registry->register(new \Platform\Locations\Tools\CreateLocationPricingTool());
            $registry->register(new \Platform\Locations\Tools\UpdateLocationPricingTool());
            $registry->register(new \Platform\Locations\Tools\DeleteLocationPricingTool());

            // Bestuhlung-Sub-Entity
            $registry->register(new \Platform\Locations\Tools\ListLocationSeatingOptionsTool());
            $registry->register(new \Platform\Locations\Tools\CreateLocationSeatingOptionTool());
            $registry->register(new \Platform\Locations\Tools\UpdateLocationSeatingOptionTool());
            $registry->register(new \Platform\Locations\Tools\DeleteLocationSeatingOptionTool());

            // Add-on-Sub-Entity
            $registry->register(new \Platform\Locations\Tools\ListLocationAddonsTool());
            $registry->register(new \Platform\Locations\Tools\CreateLocationAddonTool());
            $registry->register(new \Platform\Locations\Tools\UpdateLocationAddonTool());
            $registry->register(new \Platform\Locations\Tools\DeleteLocationAddonTool());

            // Asset-Tools (S3, Multi-Datei pro Kategorie)
            $registry->register(new \Platform\Locations\Tools\GetLocationAssetCategoriesTool());
            $registry->register(new \Platform\Locations\Tools\ListLocationAssetsTool());
            $registry->register(new \Platform\Locations\Tools\DeleteLocationAssetTool());
        } catch (\Throwable $e) {
            // Silent fail – Tool-Registry ggf. noch nicht verfügbar
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Locations\\Livewire';
        $prefix = 'locations';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
