<?php
namespace VEximweb\Plugin\DnsTools\Filament\Resources\Dmarc\Modals;

use Filament\Forms;
use VEximweb\Core\Data\Repositories\Interfaces\SettingRepositoryInterface;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use VEximweb\Plugin\DnsTools\Models\SystemDomains as Domain;

class GenerateDmarcForm
{
    protected static ?Domain $domainRecord = null;
    
    public static function extend(callable $components, callable $onSave): void
    {
        $existing = app()->bound('dmarcform.extenders')
            ? app('dmarcform.extenders')
            : ['components' => [], 'hooks' => []];

        $existing['components'][] = $components;
        $existing['hooks'][] = $onSave;

        app()->instance('dmarcform.extenders', $existing);
    }

    public static function runSaveHooks(mixed $record, array $data): void
    {
        if (! app()->bound('dmarcform.extenders')) {
            return;
        }

        foreach (app('dmarcform.extenders')['hooks'] as $hook) {
            $hook($record, $data);
        }
    }    

    /**
     * Set the domain record for the form.
     */
    public static function setDomainRecord(Domain $domain): void
    {
        static::$domainRecord = $domain;
    }

    /**
     * Get the current domain record.
     */
    protected static function getDomainRecord(): ?Domain
    {
        return static::$domainRecord;
    }

    /**
     * The Filament form schema - now accepts Domain record instead of string
     */
    public static function schema(Domain $domain): array
    {
        // Set the domain record for extensions
        static::setDomainRecord($domain);
        
        $extra = [];
        
    if (app()->bound('dmarcform.extenders')) {
        foreach (app('dmarcform.extenders')['components'] as $extender) {
            // Pass the domain record directly to the extender
            if (is_callable($extender)) {
                $result = $extender($domain);
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
                        ->label('Failure Reporting Options')
                        ->columns(5)
                        ->options([
                            '0' => 'No reports',
                            '1' => 'Summary only',
                            'd' => 'DKIM failures',
                            's' => 'SPF failures',
                            'all' => 'All failures',
                        ]),

                    TextInput::make('dmarc_rua_localpart')
                        ->label('Aggregate Reports Email')
                        ->helperText('Email address for aggregate reports (auto-appended with @' . $domain->domain . ')')
                        ->default('dmarc-reports')
                        ->prefix('📊')
                        ->suffix('@' . $domain->domain),

                    TextInput::make('dmarc_ruf_localpart')
                        ->label('Forensic Reports Email')
                        ->helperText('Email address for forensic reports (auto-appended with @' . $domain->domain . ')')
                        ->default('dmarc-forensic')
                        ->prefix('🔍')
                        ->suffix('@' . $domain->domain),
                ]),
            
            ...$extra,
        ];
    }

    /**
     * Populate the form from the repository - now accepts domain record
     */
    public static function values(SettingRepositoryInterface $settings, Domain $domain): array
    {
        // Get settings for this domain
        $rua_localpart = $settings->get('dmarc_rua_localpart', 'dmarc-reports');
        $ruf_localpart = $settings->get('dmarc_ruf_localpart', 'dmarc-forensic');
        
        return [
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

    /**
     * Save the form back to the repository - now accepts domain record
     */
    public static function save(
        SettingRepositoryInterface $settings,
        array $data,
        Domain $domain,
    ): void {
        foreach ([
            'dmarc_policy',
            'dmarc_subdomain_policy',
            'dmarc_np',
            'dmarc_psd',
            'dmarc_t',
            'dmarc_percentage',
            'dmarc_report_interval',
            'dmarc_adkim',
            'dmarc_aspf',
            'dmarc_rua_localpart',
            'dmarc_ruf_localpart',
        ] as $key) {
            $settings->set($key, $data[$key]);
        }

        $settings->set('dmarc_rua', [$data['dmarc_rua_localpart'] . '@' . $domain->domain]);
        $settings->set('dmarc_ruf', [$data['dmarc_ruf_localpart'] . '@' . $domain->domain]);
        $settings->set('dmarc_reporting', $data['dmarc_reporting'] ?? []);
    }
}