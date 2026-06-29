<?php

namespace VEximweb\Plugin\DnsTools\Filament\Providers;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use VEximweb\Plugin\DnsTools\Filament\Resources\DmarcResource;

class DnsToolsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('dns-tools')
            ->path('dns-tools')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->resources([
                DmarcResource::class,
            ])
            ->navigationGroups([
                'DNS Tools',
            ]);
    }
}
