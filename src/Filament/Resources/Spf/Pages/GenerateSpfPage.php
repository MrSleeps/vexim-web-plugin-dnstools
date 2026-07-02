<?php

namespace VEximweb\Plugin\DnsTools\Filament\Resources\Spf\Pages;

use Filament\Resources\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;
use Filament\Notifications\Notification;
use VEximweb\Core\Data\Repositories\Interfaces\SettingRepositoryInterface;
use VEximweb\Plugin\DnsTools\Models\SystemDomains as Domain;
use VEximweb\Plugin\DnsTools\Services\SpfRecordService;

class GenerateSpfPage extends Page
{
    use InteractsWithForms;

    protected string $view = 'dns-tools::filament.pages.generate-spf';

    public ?array $data = [];

    public Domain $domain;

    public ?string $generatedRecord = null;

    public ?int $lookupCount = 0;

    public ?string $dnsName = '';

    public ?array $validationIssues = [];

    public static function getResource(): string
    {
        return \VEximweb\Plugin\DnsTools\Filament\Resources\DnsToolsResource::class;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Generate SPF';
    }

    public function getTitle(): string
    {
        return 'Generate SPF Record';
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
        
        // Get domain name
        $domainName = $domain->domain;
        
        // Load existing settings if any
        $settings = app(SettingRepositoryInterface::class);
        $formData = $this->getValues($settings);
        
        // Explicitly set the domain fields
        $formData['domain'] = $domainName;
        $formData['domain_id'] = $domain->domain_id;
        
        // Fill the form
        $this->form->fill($formData);
        
        // Set DNS name
        $this->dnsName = $domainName;
        
        // Initialize validation issues
        $this->validationIssues = [];
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

        if (app()->bound('spfform.extenders')) {
            foreach (app('spfform.extenders')['components'] as $extender) {
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
            Section::make('Domain Information')
                ->columns(2)
                ->schema([
                    Hidden::make('domain_id')
                        ->default($this->domain->domain_id ?? null),
                    
                    TextInput::make('domain')
                        ->label('Domain')
                        ->default($this->domain->domain ?? '')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('The domain for which this SPF record is being generated'),
                ]),

            Section::make('Policy & Fail Settings')
                ->columns(2)
                ->schema([
                    Select::make('spf_fail_policy')
                        ->label('Fail Policy')
                        ->helperText('How receiving servers should handle SPF failures')
                        ->options([
                            '-all' => 'Hard Fail (Recommended) - Reject emails that fail SPF',
                            '~all' => 'Soft Fail - Accept but mark as suspicious',
                            '?all' => 'Neutral - Do nothing',
                            '+all' => 'Permit All (Not recommended - breaks SPF)',
                        ])
                        ->default('-all')
                        ->required(),

                    Select::make('ttl')
                        ->label('TTL (Time to Live)')
                        ->options([
                            '300' => '300 (5 minutes - testing)',
                            '3600' => '3600 (1 hour - standard)',
                            '86400' => '86400 (24 hours - stable)',
                        ])
                        ->default('3600')
                        ->required(),
                ]),

            Section::make('Sending Services (Include Mechanisms)')
                ->schema([
                    Repeater::make('spf_includes')
                        ->label('Email Sending Services')
                        ->helperText('Add the include mechanisms for your email providers (e.g., Google Workspace, SendGrid, Mailchimp)')
                        ->schema([
                            TextInput::make('service')
                                ->label('Service Include')
                                ->placeholder('e.g., spf.google.com')
                                ->helperText('Enter the domain after "include:" (e.g., "spf.google.com" not "include:spf.google.com")')
                                ->required(),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->reorderable()
                        ->addActionLabel('Add Email Service'),
                ]),

            Section::make('IP Addresses')
                ->columns(2)
                ->schema([
                    Repeater::make('spf_ipv4')
                        ->label('IPv4 Addresses')
                        ->helperText('Add dedicated IPv4 addresses or CIDR blocks')
                        ->schema([
                            TextInput::make('ipv4')
                                ->label('IPv4 Address')
                                ->placeholder('e.g., 192.0.2.0 or 192.0.2.0/24')
                                ->required()
                                ->rule('ipv4'),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->reorderable()
                        ->addActionLabel('Add IPv4 Address'),

                    Repeater::make('spf_ipv6')
                        ->label('IPv6 Addresses')
                        ->helperText('Add dedicated IPv6 addresses or CIDR blocks')
                        ->schema([
                            TextInput::make('ipv6')
                                ->label('IPv6 Address')
                                ->placeholder('e.g., 2001:db8:: or 2001:db8::/32')
                                ->required()
                                ->rule('ipv6'),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->reorderable()
                        ->addActionLabel('Add IPv6 Address'),
                ]),

            Section::make('Mail Server Settings')
                ->columns(2)
                ->schema([
                    Toggle::make('spf_use_mx')
                        ->label('Use MX Records')
                        ->helperText('Allow all mail servers listed in your domain\'s MX records to send mail')
                        ->default(false),

                    TextInput::make('spf_mx_domain')
                        ->label('Custom MX Domain')
                        ->placeholder('Optional: mail.otherdomain.com')
                        ->helperText('Use MX records from another domain (e.g., "mx:mail.otherdomain.com")')
                        ->visible(fn ($get) => $get('spf_use_mx') === true),

                    Toggle::make('spf_use_a')
                        ->label('Use A Record')
                        ->helperText('Allow the IP found in your domain\'s A record to send mail')
                        ->default(false),

                    TextInput::make('spf_a_domain')
                        ->label('Custom A Domain')
                        ->placeholder('Optional: mail.otherdomain.com')
                        ->helperText('Use A record from another domain (e.g., "a:mail.otherdomain.com")')
                        ->visible(fn ($get) => $get('spf_use_a') === true),
                ]),

            Section::make('Advanced Settings')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('spf_redirect')
                        ->label('Redirect')
                        ->placeholder('e.g., _spf.facebook.com')
                        ->helperText('Points the SPF query to another domain\'s SPF record entirely'),

                    TextInput::make('spf_exp')
                        ->label('Exp (Explanation)')
                        ->placeholder('e.g., %{i} is not authorized')
                        ->helperText('Sets an explanation text to show to receivers when an email fails (rarely used)'),

                    Select::make('spf_ptr')
                        ->label('PTR Mechanism')
                        ->options([
                            'none' => 'Do not use PTR (Recommended)',
                            'ptr' => 'Use PTR (Not recommended - deprecated)',
                        ])
                        ->default('none')
                        ->helperText('PTR is deprecated and causes massive DNS load. Use only if absolutely necessary.'),

                    TextInput::make('spf_exists')
                        ->label('Exists')
                        ->placeholder('e.g., exists.%{i}.example.com')
                        ->helperText('Complex DNS lookup query (only for power users)'),
                ]),

            ...$extra,

            Actions::make([
                Action::make('generate')
                    ->label('Generate SPF Record')
                    ->submit('generateSpf'),
            ]),
        ];
    }

    protected function getValues(SettingRepositoryInterface $settings): array
    {
        return [
            'domain_id' => $this->domain->domain_id ?? null,
            'domain' => $this->domain->domain ?? '',
            'spf_fail_policy' => $settings->get('spf_fail_policy', '-all'),
            'ttl' => $settings->get('spf_ttl', '3600'),
            'spf_includes' => $settings->get('spf_includes', []),
            'spf_ipv4' => $settings->get('spf_ipv4', []),
            'spf_ipv6' => $settings->get('spf_ipv6', []),
            'spf_use_mx' => $settings->get('spf_use_mx', false),
            'spf_mx_domain' => $settings->get('spf_mx_domain', ''),
            'spf_use_a' => $settings->get('spf_use_a', false),
            'spf_a_domain' => $settings->get('spf_a_domain', ''),
            'spf_redirect' => $settings->get('spf_redirect', ''),
            'spf_exp' => $settings->get('spf_exp', ''),
            'spf_ptr' => $settings->get('spf_ptr', 'none'),
            'spf_exists' => $settings->get('spf_exists', ''),
        ];
    }

    public function generateSpf(SpfRecordService $spf): void
    {
        $data = $this->form->getState();
        \Log::debug('SPF Form data:' . json_encode($data));
        \Log::debug('Update dms state:' . $data['update_dns']);

        // Generate the SPF record
        $result = $spf->generate($this->domain, $data);
        
        $this->generatedRecord = $result['record'];
        $this->lookupCount = $result['lookups'] ?? 0;
        $this->dnsName = $this->domain->domain ?? '';
        $this->validationIssues = $result['issues'] ?? [];

        // Log the generated record
        \Log::debug('SPF Event data:', [
            'zone' => $this->dnsName,
            'name' => '',
            'content' => $this->generatedRecord
        ]);
        if($data['update_dns']) {
            
            event(new \App\Events\SpfRecordGenerated(
                zone: $this->dnsName,
                name: '',
                type: 'TXT',
                content: $this->generatedRecord,
                ttl: (int) ($data['ttl'] ?? 3600),
                operation: 'create'
            ));
        };

        // Show the modal with the generated record
        $this->dispatch('open-modal', id: 'spf-record-modal');
    }

    // Keep the static extend method for backward compatibility
    public static function extend(callable $components, callable $onSave): void
    {
        $existing = app()->bound('spfform.extenders')
            ? app('spfform.extenders')
            : ['components' => [], 'hooks' => []];

        $existing['components'][] = $components;
        $existing['hooks'][] = $onSave;

        app()->instance('spfform.extenders', $existing);
    }
}