<?php

namespace VEximweb\Plugin\DnsTools\Filament\Resources\Dmarc\Pages;

use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Route;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;
use Filament\Notifications\Notification;
use VEximweb\Core\Data\Repositories\Interfaces\SettingRepositoryInterface;
use VEximweb\Plugin\DnsTools\Models\SystemDomains as Domain;
use VEximweb\Plugin\DnsTools\Services\DmarcRecordService;

class GenerateDmarcPage extends Page
{
    use InteractsWithForms;

    protected string $view = 'dns-tools::filament.pages.generate-dmarc';

    public ?array $data = [];

    public Domain $domain;

    public ?string $generatedRecord = null;

    public static function getResource(): string
    {
        return \VEximweb\Plugin\DnsTools\Filament\Resources\DnsToolsResource::class;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Generate DMARC';
    }

    public function getTitle(): string
    {
        return 'Generate DMARC Record';
    }
    
    public function getListeners(): array
    {
        return [
            'refreshNotifications' => 'refreshNotifications',
        ];
    }

    public function mount(Domain $domain): void
    {
        $this->domain = $domain;
        $domainId = $this->domain->id;

        // Load existing settings if any
        $settings = app(SettingRepositoryInterface::class);
        $this->form->fill($this->getValues($settings));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    /**
     * Get the form schema as an array of components
     */
    protected function getFormSchema(): array
    {
        $extra = [];

        if (app()->bound('dmarcform.extenders')) {
            foreach (app('dmarcform.extenders')['components'] as $extender) {
                if (is_callable($extender)) {
                    $result = $extender($this->domain);
                    if (is_array($result)) {
                        $extra = array_merge($extra, $result);
                    }
                } else {
                    $extra = array_merge($extra, $extender());
                }
            }
        }

        return [
            Section::make('Policy')
                ->columns(2)
                ->schema([
                    Hidden::make('domain_id')
                        ->default($this->domain->id),
                    Select::make('dmarc_policy')
                        ->label('Policy')
                        ->options([
                            'none' => 'None',
                            'quarantine' => 'Quarantine',
                            'reject' => 'Reject',
                        ])
                        ->required(),

                    Select::make('dmarc_subdomain_policy')
                        ->label('Subdomain Policy')
                        ->options([
                            '' => 'Inherit Policy',
                            'none' => 'None',
                            'quarantine' => 'Quarantine',
                            'reject' => 'Reject',
                        ]),

                    Hidden::make('dmarc_np')
                        ->default(''),

                    Hidden::make('dmarc_psd')
                        ->default('n'),

                    Hidden::make('dmarc_t')
                        ->default('n'),

                    Hidden::make('dmarc_percentage')
                        ->default(100),

                    Hidden::make('dmarc_report_interval')
                        ->default(86400),
                ]),

            Section::make('Alignment')
                ->columns(2)
                ->schema([
                    Select::make('dmarc_adkim')
                        ->label('DKIM Alignment')
                        ->options([
                            'relaxed' => 'Relaxed',
                            'strict' => 'Strict',
                        ]),

                    Select::make('dmarc_aspf')
                        ->label('SPF Alignment')
                        ->options([
                            'relaxed' => 'Relaxed',
                            'strict' => 'Strict',
                        ]),
                ]),

            Section::make('Reporting')
                ->schema([
                    CheckboxList::make('dmarc_reporting')
                        ->label('Forensic Report Triggers')
                        ->helperText('"Any" and "All" are mutually exclusive; DKIM/SPF can be combined.')
                        ->options([
                            'any' => 'Any mechanism fails (DKIM or SPF, most common) ',
                            'all' => 'All mechanisms fail (Not recommended)',
                            'dkim' => 'DKIM fails',
                            'spf' => 'SPF fails',
                        ])
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (in_array('all', $value ?? []) && in_array('any', $value ?? [])) {
                                $fail("'Any' and 'All' cannot both be selected.");
                            }
                        }),

                    TextInput::make('dmarc_rua_localpart')
                        ->label('Aggregate Reports Email')
                        ->helperText('Email address for aggregate reports (auto-appended with @' . $this->domain->domain . ')')
                        ->default('dmarc-reports')
                        ->suffix('@' . $this->domain->domain),

                    TextInput::make('dmarc_ruf_localpart')
                        ->label('Forensic Reports Email')
                        ->helperText('Email address for forensic reports (auto-appended with @' . $this->domain->domain . ')')
                        ->default('dmarc-forensic')
                        ->suffix('@' . $this->domain->domain),
                ]),

            ...$extra,

            Actions::make([
                Action::make('generate')
                    ->label('Generate DMARC Record')
                    ->submit('generateDMARC'),
            ]),
        ];
    }

    protected function getValues(SettingRepositoryInterface $settings): array
    {
        $rua_localpart = $settings->get('dmarc_rua_localpart', 'dmarc-reports');
        $ruf_localpart = $settings->get('dmarc_ruf_localpart', 'dmarc-forensic');

        return [
            'domain_id' => $this->domain->domain_id,
            'dmarc_policy' => $settings->get('dmarc_policy'),
            'dmarc_subdomain_policy' => $settings->get('dmarc_subdomain_policy'),
            'dmarc_np' => $settings->get('dmarc_np', ''),
            'dmarc_psd' => $settings->get('dmarc_psd', 'n'),
            'dmarc_t' => $settings->get('dmarc_t', 'n'),
            'dmarc_percentage' => $settings->get('dmarc_percentage', 100),
            'dmarc_report_interval' => $settings->get('dmarc_report_interval', 86400),
            'dmarc_adkim' => $settings->get('dmarc_adkim'),
            'dmarc_aspf' => $settings->get('dmarc_aspf'),
            'dmarc_reporting' => $settings->get('dmarc_reporting', []),
            'dmarc_rua_localpart' => $rua_localpart,
            'dmarc_ruf_localpart' => $ruf_localpart,
        ];
    }

    public function generateDMARC(DmarcRecordService $dmarc): void
    {
        $data = $this->form->getState();
        \Log::debug('Form data:'. json_encode($data));

        $this->generatedRecord = $dmarc->generate($this->domain, $data);

        $domainName = $this->domain->domain ?? $this->domain['domain'] ?? (string) $this->domain;

        \Log::debug('DMARC Event data:', [
            'zone' => $domainName,
            'name' => '_dmarc',
            'content' => $this->generatedRecord
        ]);
        
        if($data['update_dns']) {
            event(new \App\Events\DmarcKeyGenerated(
                zone: $domainName,
                name: '_dmarc',
                type: 'TXT',
                content: $this->generatedRecord,
                ttl: 3600,
                operation: 'create'
            ));
        };
        $this->dispatch('open-modal', id: 'dmarc-record-modal');
    }


    // Keep the static extend method for backward compatibility
    public static function extend(callable $components, callable $onSave): void
    {
        $existing = app()->bound('dmarcform.extenders')
            ? app('dmarcform.extenders')
            : ['components' => [], 'hooks' => []];

        $existing['components'][] = $components;
        $existing['hooks'][] = $onSave;

        app()->instance('dmarcform.extenders', $existing);
    }
}