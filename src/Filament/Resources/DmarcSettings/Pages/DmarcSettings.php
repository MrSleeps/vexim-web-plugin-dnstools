<?php

namespace VEximweb\Plugin\DnsTools\Filament\Resources\DmarcSettings\Pages;

use VEximweb\Plugin\DnsTools\Filament\Resources\DmarcSettingsResource;
use Filament\Schemas\Schema;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use VEximweb\Plugin\DnsTools\Models\DnsToolsSettings;

class DmarcSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    
    protected static string $resource = DmarcSettingsResource::class;
    
    protected string $view = 'pwa::filament.resources.settings.pages.edit-pwa-settings';
    
    public ?array $data = [];

    public function mount(): void
    {
        $settings = DnsToolsSettings::where('key', 'LIKE', 'dmarc_%')->get();
        
        $formData = [];
        foreach ($settings as $setting) {
            // Decode based on the type column
            switch ($setting->type) {
                case 'json':
                    $decoded = json_decode($setting->value, true);
                    $formData[$setting->key] = is_array($decoded) ? $decoded : [];
                    break;
                case 'boolean':
                    $formData[$setting->key] = (bool) $setting->value;
                    break;
                case 'integer':
                    $formData[$setting->key] = (int) $setting->value;
                    break;
                default:
                    $formData[$setting->key] = $setting->value;
                    break;
            }
        }

        // Ensure dmarc_reporting is always an array of valid values
        if (!isset($formData['dmarc_reporting']) || !is_array($formData['dmarc_reporting'])) {
            $formData['dmarc_reporting'] = ['0'];
        } else {
            // Filter to only valid values
            $validValues = ['0', '1', 'd', 's'];
            $formData['dmarc_reporting'] = array_values(array_filter($formData['dmarc_reporting'], function($value) use ($validValues) {
                return in_array($value, $validValues);
            }));
            
            // If empty, set default
            if (empty($formData['dmarc_reporting'])) {
                $formData['dmarc_reporting'] = ['0'];
            }
        }

        $this->data = $formData;
        $this->form->fill($formData);
    }	
	
    protected function getHeaderActions(): array
    {
        return [];
    }
	
	public function getBreadcrumbs(): array
	{
		return [];
	}
	
    public function getTitle(): string
    {
        return 'Edit DMARC Settings';
    }	
    
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema($this->getFormSchema())
            ->statePath('data');
    }
    
    protected function getFormSchema(): array
    {
        return [
            Tabs::make('DMARC Settings Tabs')
                ->tabs([
                    Tab::make('general')
                        ->label('General Policy')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Section::make('DMARC Policy Settings')
                                ->description('Configure main DMARC policy settings')
                                ->schema([
                                    Select::make('dmarc_policy')
                                        ->label('Policy')
                                        ->required()
                                        ->options([
                                            'none' => 'None (Monitor Only)',
                                            'quarantine' => 'Quarantine',
                                            'reject' => 'Reject',
                                        ])
                                        ->default('reject')
                                        ->helperText('DMARC policy: none, quarantine, or reject'),
                                    
                                    Select::make('dmarc_subdomain_policy')
                                        ->label('Subdomain Policy')
                                        ->options([
                                            '' => 'Inherit from parent policy',
                                            'none' => 'None (Monitor Only)',
                                            'quarantine' => 'Quarantine',
                                            'reject' => 'Reject',
                                        ])
                                        ->helperText('DMARC subdomain policy: inherit, none, quarantine, or reject'),
                                    
                                    Select::make('dmarc_np')
                                        ->label('Non-Existent Subdomain Policy')
                                        ->options([
                                            '' => 'Inherit from parent policy',
                                            'none' => 'None (Monitor Only)',
                                            'quarantine' => 'Quarantine',
                                            'reject' => 'Reject',
                                        ])
                                        ->helperText('Non-existent subdomain policy: inherit, none, quarantine, or reject'),
                                    
                                    TextInput::make('dmarc_percentage')
                                        ->label('Policy Percentage')
                                        ->required()
                                        ->numeric()
                                        ->default(100)
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->helperText('Percentage of messages to apply policy to (0-100)')
                                        ->suffix('%'),
                                ])->columns(2),
                        ]),
                    
                    Tab::make('alignment')
                        ->label('Alignment')
                        ->icon('heroicon-o-arrows-right-left')
                        ->schema([
                            Section::make('DKIM & SPF Alignment')
                                ->description('Configure DKIM and SPF alignment settings')
                                ->schema([
                                    Select::make('dmarc_adkim')
                                        ->label('DKIM Alignment')
                                        ->required()
                                        ->options([
                                            'relaxed' => 'Relaxed',
                                            'strict' => 'Strict',
                                        ])
                                        ->default('relaxed')
                                        ->helperText('DKIM alignment: relaxed or strict'),
                                    
                                    Select::make('dmarc_aspf')
                                        ->label('SPF Alignment')
                                        ->required()
                                        ->options([
                                            'relaxed' => 'Relaxed',
                                            'strict' => 'Strict',
                                        ])
                                        ->default('relaxed')
                                        ->helperText('SPF alignment: relaxed or strict'),
                                    
                                    Select::make('dmarc_psd')
                                        ->label('Public Suffix Domain Policy')
                                        ->options([
                                            '' => 'Default',
                                            'y' => 'Yes',
                                            'n' => 'No',
                                            'u' => 'User-specified',
                                        ])
                                        ->helperText('Public suffix domain policy: y (yes), n (no), or u (user-specified)'),
                                    
                                    Select::make('dmarc_t')
                                        ->label('Testing Mode')
                                        ->required()
                                        ->options([
                                            'y' => 'Yes (Testing Mode)',
                                            'n' => 'No (Production Mode)',
                                        ])
                                        ->default('n')
                                        ->helperText('Testing mode: y (yes) or n (no)'),
                                ])->columns(2),
                        ]),
                    
                    Tab::make('reporting')
                        ->label('Reporting')
                        ->icon('heroicon-o-envelope')
                        ->schema([
                            Section::make('Report Email Configuration')
                                ->description('Configure aggregate and forensic report email addresses')
                                ->schema([
                                    TagsInput::make('dmarc_rua')
                                        ->label('Aggregate Report Addresses (RUA)')
                                        ->required()
                                        ->default(['dmarc@testing.com'])
                                        ->helperText('Email addresses for aggregate reports (RUA)')
                                        ->placeholder('dmarc@example.com')
                                        ->separator(','),
                                    
                                    TextInput::make('dmarc_rua_localpart')
                                        ->label('Aggregate Report Local Part')
                                        ->required()
                                        ->default('dmarc')
                                        ->maxLength(64)
                                        ->helperText('Custom local part for aggregate (RUA) report email addresses')
                                        ->placeholder('dmarc'),
                                    
                                    TagsInput::make('dmarc_ruf')
                                        ->label('Forensic Report Addresses (RUF)')
                                        ->required()
                                        ->default(['dmarc@testing.com'])
                                        ->helperText('Email addresses for forensic reports (RUF)')
                                        ->placeholder('dmarc@example.com')
                                        ->separator(','),
                                    
                                    TextInput::make('dmarc_ruf_localpart')
                                        ->label('Forensic Report Local Part')
                                        ->required()
                                        ->default('dmarc')
                                        ->maxLength(64)
                                        ->helperText('Custom local part for forensic (RUF) report email addresses')
                                        ->placeholder('dmarc'),
                                    
                                    TextInput::make('dmarc_report_interval')
                                        ->label('Report Interval')
                                        ->required()
                                        ->numeric()
                                        ->default(86400)
                                        ->minValue(300)
                                        ->helperText('Reporting interval in seconds (default 24 hours)')
                                        ->suffix('seconds')
                                        ->hint('24 hours = 86400 seconds'),
                                    
                                    Select::make('dmarc_reporting')
                                        ->label('Failure Reporting Options (fo)')
                                        ->required()
                                        ->options([
                                            '0' => '0 - Report failures only',
                                            '1' => '1 - Report all failures',
                                            'd' => 'd - Report DKIM failures only',
                                            's' => 's - Report SPF failures only',
                                        ])
                                        ->default(['0'])
                                        ->multiple()
                                        ->helperText('Select one or more failure reporting options')
                                        ->rules(['array', 'min:1'])
                                        ->validationAttribute('failure reporting options')
                                        ->afterStateHydrated(function ($component, $state) {
                                            // Ensure state is always an array of valid values
                                            $validValues = ['0', '1', 'd', 's'];
                                            
                                            if (!is_array($state)) {
                                                // Try to decode if it's a JSON string
                                                if (is_string($state)) {
                                                    $decoded = json_decode($state, true);
                                                    if (is_array($decoded)) {
                                                        $state = $decoded;
                                                    } else {
                                                        $state = ['0'];
                                                    }
                                                } else {
                                                    $state = ['0'];
                                                }
                                            }
                                            
                                            // Filter to only valid values
                                            $state = array_values(array_filter($state, function($value) use ($validValues) {
                                                return in_array((string)$value, $validValues);
                                            }));
                                            
                                            // If empty, set default
                                            if (empty($state)) {
                                                $state = ['0'];
                                            }
                                            
                                            $component->state($state);
                                        }),
                                ])->columns(2),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }
    
    public function save(): void
    {
        try {
            $state = $this->form->getState();
            
            // Get the existing settings to know their types
            $existingSettings = DnsToolsSettings::where('key', 'LIKE', 'dmarc_%')->get()->keyBy('key');
            
            foreach ($state as $key => $value) {
                $type = 'string';
                
                // Determine the type based on the key or existing record
                if (isset($existingSettings[$key])) {
                    $type = $existingSettings[$key]->type;
                } else {
                    // Guess the type based on the key name
                    if (in_array($key, ['dmarc_reporting', 'dmarc_rua', 'dmarc_ruf'])) {
                        $type = 'json';
                    } elseif (in_array($key, ['dmarc_percentage', 'dmarc_report_interval'])) {
                        $type = 'integer';
                    } elseif (in_array($key, ['dmarc_t'])) {
                        $type = 'boolean';
                    }
                }
                
                // Convert the value based on its type
                switch ($type) {
                    case 'json':
                        if (is_array($value)) {
                            // For dmarc_reporting, ensure only valid values are saved
                            if ($key === 'dmarc_reporting') {
                                $validValues = ['0', '1', 'd', 's'];
                                $value = array_values(array_filter($value, function($item) use ($validValues) {
                                    return in_array((string)$item, $validValues);
                                }));
                                
                                // If empty, set default
                                if (empty($value)) {
                                    $value = ['0'];
                                }
                            }
                            
                            // Filter out empty values and reindex
                            $value = array_values(array_filter($value, function($item) {
                                return $item !== null && $item !== '' && $item !== false;
                            }));
                            $value = json_encode($value);
                        } else {
                            $value = '[]';
                        }
                        break;
                        
                    case 'boolean':
                        $value = $value ? '1' : '0';
                        break;
                        
                    case 'integer':
                        $value = (string) (int) $value;
                        break;
                        
                    default:
                        if (is_array($value)) {
                            $value = json_encode($value);
                        } else {
                            $value = (string) $value;
                        }
                        break;
                }
                
                // Update or create the setting with the correct type
                DnsToolsSettings::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'type' => $type,
                        'category' => 'dmarc',
                    ]
                );
            }
            
            Notification::make()
                ->title('All DMARC settings saved successfully')
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving DMARC settings')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save All Settings')
                ->action('save')
                ->color('primary'),
                
            Action::make('back')
                ->label('Back to List')
                ->url(DmarcSettingsResource::getUrl('index'))
                ->color('gray'),
        ];
    }
}