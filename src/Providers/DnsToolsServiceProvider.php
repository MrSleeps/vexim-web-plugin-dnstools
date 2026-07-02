<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Providers;

use Illuminate\Support\ServiceProvider;
use VEximweb\Plugin\DnsTools\Services\DnsToolsService;
use VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService;
use Filament\Panel;
//use VEximweb\Plugin\DnsTools\Filament\Providers\DnsToolsPanelProvider;

class DnsToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge config
        /*
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/dmarc.php',
            'dmarc'
        );
        */

        // Register DMARC record service
        $this->app->singleton('dmarc.record.service', function ($app) {
            // Try to get setting repository from container
            $settingRepository = null;
            try {
                if ($app->has('VEximweb\Core\Data\Repositories\Interfaces\SettingRepositoryInterface')) {
                    $settingRepository = $app->make('VEximweb\Core\Data\Repositories\Interfaces\SettingRepositoryInterface');
                }
            } catch (\Exception $e) {
                // Setting repository not available, continue without it
            }
            
            return new DmarcRecordService($settingRepository);
        });

        // Register main service
        $this->app->singleton('dns-tools.service', function ($app) {
            return new DnsToolsService();
        });
        
        Panel::configureUsing(function (Panel $panel) {
            $panel->plugin(DnsToolsPlugin::make());
        });        
        
        //$this->app->register(DnsToolsPanelProvider::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/dmarc.php' => config_path('dmarc.php'),
        ], 'dmarc-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load routes
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'dns-tools');

        // Load translations
        //$this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'dns-tools');

        
    if (class_exists(\Filament\Panel::class)) {
        // The resource will be auto-discovered if you use:
        // $panel->discoverResources() in your panel provider
        // Or register it manually:
        \Filament\Facades\Filament::registerResources([
            \VEximweb\Plugin\DnsTools\Filament\Resources\DnsToolsResource::class,
        ]);
    }        
        
        if ($this->app->runningInConsole()) {
            $this->commands([
                \VEximweb\Plugin\DnsTools\Console\Commands\CheckDmarcRecords::class,
                \VEximweb\Plugin\DnsTools\Console\Commands\CheckSpfRecords::class,
            ]);
        }        
    }
}