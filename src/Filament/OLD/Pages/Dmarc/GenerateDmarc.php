<?php

namespace VEximweb\Plugin\DnsTools\Filament\Resources\DmarcResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Placeholder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use VEximweb\Plugin\DnsTools\Filament\Resources\DmarcResource;
use VEximweb\Plugin\DnsTools\Models\DmarcCheck;
use VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService;
use VEximweb\Plugin\DnsTools\Dmarc\DmarcRecord;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcPolicy;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcAlignment;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcReporting;
use VEximweb\Core\Data\Models\Domain;

class GenerateDmarc extends CreateRecord
{
    protected static string $resource = DmarcResource::class;

    public Domain $domain;
    public ?DmarcCheck $existingDmarc = null;

    public function mount($record = null): void
    {
        if ($record) {
            $this->domain = Domain::find($record);
            $this->existingDmarc = DmarcCheck::where('domain', $this->domain->domain)->first();
        }

        parent::mount();

        if ($this->existingDmarc) {
            $defaults = [
                'policy' => $this->existingDmarc->policy ?? 'none',
                'subdomain_policy' => $this->existingDmarc->subdomain_policy ?? null,
                'adkim' => $this->existingDmarc->adkim ?? 'relaxed',
                'aspf' => $this->existingDmarc->aspf ?? 'relaxed',
                'reporting' => json_decode($this->existingDmarc->reporting ?? '["all"]', true),
                'percentage' => $this->existingDmarc->percentage ?? 100,
                't' => $this->existingDmarc->t ?? 'n',
            ];

            if ($this->existingDmarc->rua) {
                $rua = json_decode($this->existingDmarc->rua, true);
                if (is_array($rua)) {
                    $defaults['rua'] = collect($rua)->map(function ($item) {
                        return ['email' => str_replace('mailto:', '', $item)];
                    })->toArray();
                }
            }

            if ($this->existingDmarc->ruf) {
                $ruf = json_decode($this->existingDmarc->ruf, true);
                if (is_array($ruf)) {
                    $defaults['ruf'] = collect($ruf)->map(function ($item) {
                        return ['email' => str_replace('mailto:', '', $item)];
                    })->toArray();
                }
            }

            $this->form->fill($defaults);
        }
    }

    public function form(Form $form): Form
    {
        $service = app(DmarcCheckService::class);
        $addresses = $service->getDefaultReportingAddresses($this->domain->domain ?? config('app.domain'));

        return $form
            ->schema([
                Select::make('policy')
                    ->label('Policy')
                    ->options([
                        'none' => 'None (Monitor only)',
                        'quarantine' => 'Quarantine (Mark as spam)',
                        'reject' => 'Reject (Block delivery)',
                    ])
                    ->required()
                    ->default('none')
                    ->live(),

                Select::make('subdomain_policy')
                    ->label('Subdomain Policy')
                    ->options([
                        '' => 'Inherit from main policy',
                        'none' => 'None (Monitor only)',
                        'quarantine' => 'Quarantine (Mark as spam)',
                        'reject' => 'Reject (Block delivery)',
                    ])
                    ->nullable(),

                Repeater::make('rua')
                    ->label('Aggregate Reports (RUA)')
                    ->schema([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->prefix('mailto:')
                            ->required(),
                    ])
                    ->default(function () use ($addresses) {
                        if ($this->existingDmarc && $this->existingDmarc->rua) {
                            $rua = json_decode($this->existingDmarc->rua, true);
                            if (is_array($rua)) {
                                return collect($rua)->map(function ($item) {
                                    return ['email' => str_replace('mailto:', '', $item)];
                                })->toArray();
                            }
                        }
                        return [['email' => str_replace('mailto:', '', $addresses['rua'])]];
                    })
                    ->addable()
                    ->deletable()
                    ->reorderable()
                    ->columnSpanFull(),

                Repeater::make('ruf')
                    ->label('Forensic Reports (RUF)')
                    ->schema([
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->prefix('mailto:')
                            ->required(),
                    ])
                    ->default(function () use ($addresses) {
                        if ($this->existingDmarc && $this->existingDmarc->ruf) {
                            $ruf = json_decode($this->existingDmarc->ruf, true);
                            if (is_array($ruf)) {
                                return collect($ruf)->map(function ($item) {
                                    return ['email' => str_replace('mailto:', '', $item)];
                                })->toArray();
                            }
                        }
                        return [['email' => str_replace('mailto:', '', $addresses['ruf'])]];
                    })
                    ->addable()
                    ->deletable()
                    ->reorderable()
                    ->columnSpanFull(),

                Select::make('adkim')
                    ->label('DKIM Alignment')
                    ->options([
                        'relaxed' => 'Relaxed (subdomains allowed)',
                        'strict' => 'Strict (exact match)',
                    ])
                    ->default('relaxed'),

                Select::make('aspf')
                    ->label('SPF Alignment')
                    ->options([
                        'relaxed' => 'Relaxed (subdomains allowed)',
                        'strict' => 'Strict (exact match)',
                    ])
                    ->default('relaxed'),

                Select::make('reporting')
                    ->label('Failure Reporting (FO)')
                    ->multiple()
                    ->options([
                        'all' => 'All failures',
                        'any' => 'Any failure',
                        'dkim' => 'DKIM failures only',
                        'spf' => 'SPF failures only',
                    ])
                    ->default(['all']),

                Slider::make('percentage')
                    ->label('Enforcement Percentage')
                    ->min(0)
                    ->max(100)
                    ->default(100),

                Select::make('t')
                    ->label('Testing Mode')
                    ->options([
                        'n' => 'Off (Apply policy)',
                        'y' => 'On (Don\'t apply policy)',
                    ])
                    ->default('n'),

                Placeholder::make('generated_record_preview')
                    ->label('Preview')
                    ->content(function ($get) {
                        try {
                            $settings = $this->prepareSettings($get);
                            $dmarcRecord = new DmarcRecord(
                                policy: DmarcPolicy::tryFrom($settings['policy']),
                                subdomainPolicy: $settings['subdomain_policy'] ? DmarcPolicy::tryFrom($settings['subdomain_policy']) : null,
                                rua: $settings['rua'],
                                ruf: $settings['ruf'],
                                adkim: DmarcAlignment::tryFrom($settings['adkim']),
                                aspf: DmarcAlignment::tryFrom($settings['aspf']),
                                reporting: array_map(fn($r) => DmarcReporting::tryFrom($r), $settings['reporting']),
                                percentage: (int)$settings['percentage'],
                                t: $settings['t'],
                            );
                            
                            if ($this->domain) {
                                return "Name: _dmarc.{$this->domain->domain}\n" .
                                       "Type: TXT\n" .
                                       "Value: v=DMARC1; " . $dmarcRecord->toDnsRecord();
                            }
                            return "Please select a domain to preview";
                        } catch (\Exception $e) {
                            return "Error generating preview: " . $e->getMessage();
                        }
                    })
                    ->extraAttributes(['class' => 'font-mono text-sm bg-gray-50 dark:bg-gray-800 p-4 rounded'])
                    ->columnSpanFull(),
            ]);
    }

    protected function prepareSettings(array $data): array
    {
        return [
            'policy' => $data['policy'] ?? 'none',
            'subdomain_policy' => $data['subdomain_policy'] ?? null,
            'rua' => collect($data['rua'] ?? [])
                ->filter(fn($item) => !empty($item['email']))
                ->map(fn($item) => 'mailto:' . $item['email'])
                ->values()
                ->toArray(),
            'ruf' => collect($data['ruf'] ?? [])
                ->filter(fn($item) => !empty($item['email']))
                ->map(fn($item) => 'mailto:' . $item['email'])
                ->values()
                ->toArray(),
            'adkim' => $data['adkim'] ?? 'relaxed',
            'aspf' => $data['aspf'] ?? 'relaxed',
            'reporting' => $data['reporting'] ?? ['all'],
            'percentage' => (int)($data['percentage'] ?? 100),
            't' => $data['t'] ?? 'n',
        ];
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $settings = $this->prepareSettings($data);
        
        $dmarcRecord = new DmarcRecord(
            policy: DmarcPolicy::tryFrom($settings['policy']),
            subdomainPolicy: $settings['subdomain_policy'] ? DmarcPolicy::tryFrom($settings['subdomain_policy']) : null,
            rua: $settings['rua'],
            ruf: $settings['ruf'],
            adkim: DmarcAlignment::tryFrom($settings['adkim']),
            aspf: DmarcAlignment::tryFrom($settings['aspf']),
            reporting: array_map(fn($r) => DmarcReporting::tryFrom($r), $settings['reporting']),
            percentage: (int)$settings['percentage'],
            t: $settings['t'],
        );

        $dnsRecord = 'v=DMARC1; ' . $dmarcRecord->toDnsRecord();

        return DmarcCheck::updateOrCreate(
            ['domain' => $this->domain->domain],
            [
                'domain_id' => $this->domain->domain_id,
                'record' => $dnsRecord,
                'policy' => $settings['policy'],
                'subdomain_policy' => $settings['subdomain_policy'],
                'adkim' => $settings['adkim'],
                'aspf' => $settings['aspf'],
                'rua' => json_encode($settings['rua']),
                'ruf' => json_encode($settings['ruf']),
                'reporting' => json_encode($settings['reporting']),
                'percentage' => (int)$settings['percentage'],
                't' => $settings['t'],
                'valid' => true,
                'error_message' => null,
                'last_checked_at' => now(),
                'next_check_at' => now()->addHours(24),
            ]
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return "DMARC record generated for {$this->domain->domain}";
    }

    protected function getRedirectUrl(): string
    {
        return DmarcResource::getUrl('view', ['record' => $this->domain->getKey()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to View')
                ->icon('heroicon-o-arrow-left')
                ->url(DmarcResource::getUrl('view', ['record' => $this->domain->getKey()])),

            Action::make('back_to_list')
                ->label('Back to List')
                ->icon('heroicon-o-list-bullet')
                ->url(DmarcResource::getUrl('index')),
        ];
    }

    public function getTitle(): string
    {
        $action = $this->existingDmarc ? 'Update' : 'Generate';
        return "{$action} DMARC Record";
    }
}