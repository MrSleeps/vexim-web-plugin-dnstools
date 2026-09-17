<?php

declare(strict_types=1);

namespace VEximweb\Plugin\DnsTools\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schedule;
use VEximweb\Plugin\DnsTools\Services\DnsToolsService;
use VEximweb\Plugin\DnsTools\Services\DirectDnsResolver;
use VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService;
use Filament\Panel;

class DnsToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/dns-tools.php', 'dns-tools');

        $this->app->singleton(DirectDnsResolver::class, function () {
            return new DirectDnsResolver(
                config('dns-tools.nameservers', ['1.1.1.1', '1.0.0.1']),
                (float) config('dns-tools.timeout', 5.0),
            );
        });

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

        $this->publishes([
            __DIR__ . '/../../config/dns-tools.php' => config_path('dns-tools.php'),
        ], 'dns-tools-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'dns-tools');
        
        // Register commands and schedule
        if ($this->app->runningInConsole()) {
            $this->commands([
                \VEximweb\Plugin\DnsTools\Console\Commands\CheckDmarcRecords::class,
                \VEximweb\Plugin\DnsTools\Console\Commands\CheckSpfRecords::class,
                \VEximweb\Plugin\DnsTools\Console\Commands\CheckMtaStsRecords::class,
            ]);

            // Schedule commands - now using the facade correctly
            Schedule::command('vw:dmarc-check')->cron('10 */12 * * *'); // Every 12 hours at minute 0
            Schedule::command('vw:spf-check')->cron('15 */12 * * *');  // Every 12 hours at minute 30
            Schedule::command('vw:mta-sts-check')->cron('20 */12 * * *');  // Every 12 hours at minute 30
        }

        if (class_exists(\Filament\Panel::class)) {
            \Filament\Facades\Filament::registerResources([
                \VEximweb\Plugin\DnsTools\Filament\Resources\DnsToolsResource::class,
            ]);
        }        
    }
}