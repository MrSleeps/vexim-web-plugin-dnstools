<?php

namespace VEximweb\Plugin\PWA\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use VEximweb\Plugin\PWA\Filament\Resources\PwaSettingsResource\Schemas\SettingForm;
use VEximweb\Plugin\PWA\Filament\Resources\PwaSettingsResource\Pages\EditPWASettings;
use VVEximweb\Plugin\PWA\Models\PWASettings;
use VEximweb\Core\Data\Repositories\SettingRepository;


//use VEximweb\Plugin\PWA\Filament\Resources\PwaSettingsResource\Pages\EditPWASettings;

class PwaSettingsResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    
    protected static ?string $label = 'DMARC';
    
    protected static ?string $pluralLabel = 'DMARC';
    
    protected static ?string $model = PWASettings::class;
    
    protected static bool $shouldRegisterNavigation = true;
    
    protected SettingRepository $settingRepository;

    /**
     * Override the resource name for routing
     */
    protected static ?string $slug = 'dmarc-settings';
    
    public function boot(): void
    {
        $this->settingRepository = app(SettingRepository::class);
    }
    
    public function mount(): void
    {
        // Load all current PWA settings
        //$settings = $this->getPwaSettings();

        // Format settings for the form
     //   $this->form->fill($settings);
    }    
    
    protected function getPwaSettings(): array
    {
        $allSettings = $this->settingRepository->getAll();

        // Filter only PWA settings
        return array_filter($allSettings, function ($key) {
            return str_starts_with($key, 'dmarc_');
        }, ARRAY_FILTER_USE_KEY);
    }    
    
    public static function form(Schema $schema): Schema
    {
        return SettingForm::configure($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => EditPWASettings::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return null;
    }

    /**
     * Get the route name prefix
     */
    public static function getRouteNamePrefix(): string
    {
        return 'dmarc-settings';
    }
}