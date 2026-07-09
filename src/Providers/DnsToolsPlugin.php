<?php

namespace VEximweb\Plugin\DnsTools\Providers;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\File;
use VEximweb\Plugin\DnsTools\Filament\Resources\DnsToolsResource;
use VEximweb\Plugin\DnsTools\Filament\Resources\DmarcSettingsResource;

class DnsToolsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'dns-tools';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            DnsToolsResource::class,
            DmarcSettingsResource::class,
        ]);        
        
        $widgetPath = __DIR__ . '/Filament/Widgets';
        if (is_dir($widgetPath)) {
            $widgetClasses = $this->discoverWidgets($widgetPath);
            $panel->widgets($widgetClasses);
        }   
        
     
    }

    public function boot(Panel $panel): void {}
    
    protected function discoverWidgets(string $path): array
    {
        $widgets = [];
        
        /*
        $files = File::allFiles($path);
        
        foreach ($files as $file) {
            $class = 'VEximweb\\Plugin\\DMARC\\Filament\\Widgets\\' . $file->getFilenameWithoutExtension();
            if (class_exists($class)) {
                $widgets[] = $class;
            }
        }
        
        */
        
        return $widgets;
    }     
}
