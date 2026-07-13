<?php

namespace VEximweb\Plugin\DnsTools\Filament\Resources\Tables;

use VEximweb\Plugin\DnsTools\Models\SystemDomains;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use VEximweb\Core\EximUser\Filament\Resources\EximUserResource;
use VEximweb\Core\EximAlias\Filament\Resources\EximAliasResource;
use VEximweb\Core\EximCatchAll\Filament\Resources\EximCatchAllResource;
use VEximweb\Core\EximFail\Filament\Resources\EximFailResource;
use Filament\Support\Icons\Heroicon;
use VEximweb\Plugin\DnsTools\Models\DmarcCheck;
use Filament\Notifications\Notification;
use Filament\Actions\ActionGroup;
use VEximweb\Plugin\DnsTools\Filament\Resources\Dmarc\Modals\GenerateDmarcForm;
use VEximweb\Core\Data\Repositories\Interfaces\SettingRepositoryInterface;
use VEximweb\Plugin\DnsTools\Filament\Resources\DnsToolsResource;
use VEximweb\Plugin\DnsTools\Services\DKIMKeyService;
use Illuminate\Support\Str;
use VEximweb\Plugin\DnsTools\Models\MtaStsCheck;
use VEximweb\Plugin\MTASTS\Models\MtaSts;
use VEximweb\Plugin\PDNS\Filament\MtaStsFormExtension;

class DomainsTable
{   
    
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                    
                IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                    
                IconColumn::make('dkim.enabled')
                    ->alignCenter()
                    ->label('DKIM')
                    ->boolean()
                    ->trueIcon('heroicon-o-key')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(function ($record): string {
                        if ($record->dkim && $record->dkim->enabled) {
                            return "DKIM active (selector: {$record->dkim->selector})";
                        } elseif ($record->dkim && !$record->dkim->enabled) {
                            return "DKIM keys exist but are disabled";
                        } else {
                            return "No DKIM keys configured";
                        }
                    }),
                
                // SPF Status Column
                IconColumn::make('spf_status')
                    ->label('SPF')
                    ->alignCenter()
                    ->getStateUsing(function ($record): string {
                        $spf = $record->spfCheck;
                        
                        if (!$spf) {
                            return 'not_checked';
                        }
                        
                        if ($spf->valid) {
                            return 'valid';
                        }
                        
                        if ($spf->record === null) {
                            return 'no_record';
                        }
                        
                        return 'invalid';
                    })
                    ->icon(function ($state): string {
                        return match($state) {
                            'valid' => 'heroicon-o-shield-check',
                            'invalid' => 'heroicon-o-exclamation-triangle',
                            'no_record' => 'heroicon-o-x-circle',
                            'not_checked' => 'heroicon-o-question-mark-circle',
                        };
                    })
                    ->color(function ($state): string {
                        return match($state) {
                            'valid' => 'success',
                            'invalid' => 'danger',
                            'no_record' => 'warning',
                            'not_checked' => 'gray',
                        };
                    })
                    ->tooltip(function ($record): string {
                        $spf = $record->spfCheck;
                        
                        if (!$spf) {
                            return 'SPF not checked yet';
                        }
                        
                        if ($spf->valid) {
                            $tooltip = "SPF valid";
                            if ($spf->policy) {
                                $tooltip .= " (Policy: {$spf->getPolicyLabel()})";
                            }
                            if ($spf->lookup_count > 0) {
                                $tooltip .= " - {$spf->lookup_count} lookups";
                            }
                            return $tooltip;
                        }
                        
                        if ($spf->record === null) {
                            return 'No SPF record found';
                        }
                        
                        return "SPF invalid: {$spf->error_message}";
                    }),
                
                // DMARC Status Column (already exists)
                IconColumn::make('dmarc_status')
                    ->label('DMARC')
                    ->alignCenter()
                    ->getStateUsing(function ($record): string {
                        $dmarc = $record->dmarcCheck;
                        
                        if (!$dmarc) {
                            return 'not_checked';
                        }
                        
                        return $dmarc->valid ? 'valid' : 'invalid';
                    })
                    ->icon(function ($state): string {
                        return match($state) {
                            'valid' => 'heroicon-o-shield-check',
                            'invalid' => 'heroicon-o-exclamation-triangle',
                            'not_checked' => 'heroicon-o-question-mark-circle',
                        };
                    })
                    ->color(function ($state): string {
                        return match($state) {
                            'valid' => 'success',
                            'invalid' => 'danger',
                            'not_checked' => 'gray',
                        };
                    })
                    ->tooltip(function ($record): string {
                        $dmarc = $record->dmarcCheck;
                        
                        if (!$dmarc) {
                            return 'DMARC not checked yet';
                        }
                        
                        if ($dmarc->valid) {
                            return "DMARC valid (Policy: {$dmarc->getPolicyLabel()})";
                        }
                        
                        return "DMARC invalid: {$dmarc->error_message}";
                    }),
                
                // MTA-STS Status Column
                IconColumn::make('mta_sts_status')
                    ->label('MTA-STS')
                    ->alignCenter()
                    ->getStateUsing(function ($record): string {
                        $mtaSts = $record->mtaStsCheck;
                        
                        if (!$mtaSts) {
                            return 'not_checked';
                        }
                        
                        // DNS valid AND policy valid = full valid
                        if ($mtaSts->dns_valid && $mtaSts->policy_valid) {
                            return 'valid';
                        }
                        
                        // DNS valid but policy missing/invalid = partial
                        if ($mtaSts->dns_valid && !$mtaSts->policy_valid) {
                            return 'partial';
                        }
                        
                        // DNS invalid but checked
                        if (!$mtaSts->dns_valid && $mtaSts->checked_at) {
                            return 'invalid';
                        }
                        
                        return 'not_checked';
                    })
                    ->icon(function ($state): string {
                        return match($state) {
                            'valid' => 'heroicon-o-shield-check',
                            'partial' => 'heroicon-o-exclamation-triangle',
                            'invalid' => 'heroicon-o-x-circle',
                            'not_checked' => 'heroicon-o-question-mark-circle',
                        };
                    })
                    ->color(function ($state): string {
                        return match($state) {
                            'valid' => 'success',
                            'partial' => 'warning',
                            'invalid' => 'danger',
                            'not_checked' => 'gray',
                        };
                    })
                    ->tooltip(function ($record): string {
                        $mtaSts = $record->mtaStsCheck;
                        
                        if (!$mtaSts) {
                            return 'MTA-STS not checked yet';
                        }
                        
                        if ($mtaSts->dns_valid && $mtaSts->policy_valid) {
                            $tooltip = "MTA-STS fully configured";
                            if ($mtaSts->dns_mode) {
                                $tooltip .= " (Mode: {$mtaSts->getModeLabel()})";
                            }
                            if ($mtaSts->dns_max_age) {
                                $tooltip .= " - Max Age: {$mtaSts->dns_max_age}s";
                            }
                            if ($mtaSts->mx_mismatch) {
                                $tooltip .= " MX Mismatch detected!";
                            }
                            return $tooltip;
                        }
                        
                        if ($mtaSts->dns_valid && !$mtaSts->policy_valid) {
                            $tooltip = "MTA-STS DNS record found";
                            if ($mtaSts->error_message) {
                                $tooltip .= " - Policy issue: {$mtaSts->error_message}";
                            }
                            return $tooltip;
                        }
                        
                        if (!$mtaSts->dns_valid) {
                            return "MTA-STS invalid: {$mtaSts->error_message}";
                        }
                        
                        return 'MTA-STS not checked yet';
                    }),
            ])
            ->filters([
                SelectFilter::make('enabled')
                    ->options([
                        '1' => 'Enabled',
                        '0' => 'Disabled',
                    ]),
                    
                SelectFilter::make('dkim_status')
                    ->label('DKIM Status')
                    ->options([
                        'has_dkim' => 'Has DKIM (Active)',
                        'has_disabled' => 'Has DKIM (Disabled)',
                        'no_dkim' => 'No DKIM',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'has_dkim') {
                            return $query->whereHas('dkim', function ($q) {
                                $q->where('enabled', true);
                            });
                        }
                        if ($data['value'] === 'has_disabled') {
                            return $query->whereHas('dkim', function ($q) {
                                $q->where('enabled', false);
                            });
                        }
                        if ($data['value'] === 'no_dkim') {
                            return $query->whereDoesntHave('dkim');
                        }
                        return $query;
                    }),
                    
                // SPF Status Filter
                SelectFilter::make('spf_status')
                    ->label('SPF Status')
                    ->options([
                        'valid' => 'Valid SPF',
                        'invalid' => 'Invalid SPF',
                        'no_record' => 'No SPF Record',
                        'not_checked' => 'Not Checked',
                        'high_lookups' => 'High Lookups (>10)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'valid') {
                            return $query->whereHas('spfCheck', function ($q) {
                                $q->where('valid', true);
                            });
                        }
                        if ($data['value'] === 'invalid') {
                            return $query->whereHas('spfCheck', function ($q) {
                                $q->where('valid', false)->whereNotNull('record');
                            });
                        }
                        if ($data['value'] === 'no_record') {
                            return $query->whereHas('spfCheck', function ($q) {
                                $q->whereNull('record');
                            });
                        }
                        if ($data['value'] === 'not_checked') {
                            return $query->whereDoesntHave('spfCheck');
                        }
                        if ($data['value'] === 'high_lookups') {
                            return $query->whereHas('spfCheck', function ($q) {
                                $q->where('lookup_count', '>', 10);
                            });
                        }
                        return $query;
                    }),
                    
                // SPF Policy Filter
                SelectFilter::make('spf_policy')
                    ->label('SPF Policy')
                    ->options([
                        '-all' => 'Hard Fail (Reject)',
                        '~all' => 'Soft Fail (Mark)',
                        '?all' => 'Neutral',
                        '+all' => 'Pass (DANGEROUS)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value']) {
                            return $query->whereHas('spfCheck', function ($q) use ($data) {
                                $q->where('policy', $data['value'])
                                  ->where('valid', true);
                            });
                        }
                        return $query;
                    }),
                
                // DMARC Status Filter (already exists)
                SelectFilter::make('dmarc_status')
                    ->label('DMARC Status')
                    ->options([
                        'valid' => 'Valid DMARC',
                        'invalid' => 'Invalid DMARC',
                        'not_checked' => 'Not Checked',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'valid') {
                            return $query->whereHas('dmarcCheck', function ($q) {
                                $q->where('valid', true);
                            });
                        }
                        if ($data['value'] === 'invalid') {
                            return $query->whereHas('dmarcCheck', function ($q) {
                                $q->where('valid', false);
                            });
                        }
                        if ($data['value'] === 'not_checked') {
                            return $query->whereDoesntHave('dmarcCheck');
                        }
                        return $query;
                    }),
                    
                // DMARC Policy Filter
                SelectFilter::make('dmarc_policy')
                    ->label('DMARC Policy')
                    ->options([
                        'none' => 'Monitor (none)',
                        'quarantine' => 'Quarantine',
                        'reject' => 'Reject',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value']) {
                            return $query->whereHas('dmarcCheck', function ($q) use ($data) {
                                $q->where('policy', $data['value'])
                                  ->where('valid', true);
                            });
                        }
                        return $query;
                    }),
                
                // MTA-STS Status Filter
                SelectFilter::make('mta_sts_status')
                    ->label('MTA-STS Status')
                    ->options([
                        'valid' => 'Valid MTA-STS',
                        'partial' => 'Partial (DNS only)',
                        'invalid' => 'Invalid MTA-STS',
                        'not_checked' => 'Not Checked',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'valid') {
                            return $query->whereHas('mtaStsCheck', function ($q) {
                                $q->where('dns_valid', true)
                                  ->where('policy_valid', true);
                            });
                        }
                        if ($data['value'] === 'partial') {
                            return $query->whereHas('mtaStsCheck', function ($q) {
                                $q->where('dns_valid', true)
                                  ->where('policy_valid', false);
                            });
                        }
                        if ($data['value'] === 'invalid') {
                            return $query->whereHas('mtaStsCheck', function ($q) {
                                $q->where('dns_valid', false);
                            });
                        }
                        if ($data['value'] === 'not_checked') {
                            return $query->whereDoesntHave('mtaStsCheck');
                        }
                        return $query;
                    }),
                    
                // MTA-STS Mode Filter
                SelectFilter::make('mta_sts_mode')
                    ->label('MTA-STS Mode')
                    ->options([
                        'enforce' => 'Enforce (Strict)',
                        'testing' => 'Testing',
                        'none' => 'None (Disabled)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value']) {
                            return $query->whereHas('mtaStsCheck', function ($q) use ($data) {
                                $q->where('dns_mode', $data['value'])
                                  ->where('policy_valid', true);
                            });
                        }
                        return $query;
                    }),
                    
                // MX Mismatch Filter
                SelectFilter::make('mx_mismatch')
                    ->label('MX Mismatch')
                    ->options([
                        '1' => 'Has MX Mismatch',
                        '0' => 'No MX Mismatch',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] !== null && $data['value'] !== '') {
                            return $query->whereHas('mtaStsCheck', function ($q) use ($data) {
                                $q->where('mx_mismatch', (bool)$data['value']);
                            });
                        }
                        return $query;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    // Check DMARC Action
                    Action::make('checkDmarc')
                        ->label('Check DMARC')
                        ->tooltip('Check DMARC record now')
                        ->color('info')
                        ->action(function ($record) {
                            try {
                                $service = app(\VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService::class);
                                $result = $service->checkDomain($record->domain, $record->domain_id);
                                
                                if ($result && $result->valid) {
                                    Notification::make()
                                        ->title('DMARC check completed')
                                        ->body("Valid DMARC record found for {$record->domain}")
                                        ->success()
                                        ->send();
                                } else {
                                    $error = $result ? $result->error_message : 'Unknown error';
                                    Notification::make()
                                        ->title('DMARC check completed')
                                        ->body("No valid DMARC record for {$record->domain}: {$error}")
                                        ->warning()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('DMARC check failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                        
                    // Check SPF Action
                    Action::make('checkSpf')
                        ->label('Check SPF')
                        ->tooltip('Check SPF record now')
                        ->color('info')
                        ->action(function ($record) {
                            try {
                                $service = app(\VEximweb\Plugin\DnsTools\Services\SpfRecordService::class);
                                $result = $service->checkDomain($record->domain);
                                
                                if ($result && $result->valid) {
                                    Notification::make()
                                        ->title('SPF check completed')
                                        ->body("Valid SPF record found for {$record->domain}")
                                        ->success()
                                        ->send();
                                } else {
                                    $error = $result ? $result->error_message : 'Unknown error';
                                    Notification::make()
                                        ->title('SPF check completed')
                                        ->body("No valid SPF record for {$record->domain}: {$error}")
                                        ->warning()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('SPF check failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // Check MTA-STS Action (READ-ONLY)
                    Action::make('checkMtaSts')
                        ->label('Check MTA-STS')
                        ->tooltip('Check MTA-STS record now')
                        ->color('info')
                        ->action(function ($record) {
                            try {
                                $service = app(\VEximweb\Plugin\DnsTools\Services\MtaStsService::class);
                                $result = $service->checkDomain($record->domain, $record->domain_id);

                                if ($result && $result->valid) {
                                    $message = "Valid MTA-STS configuration found for {$record->domain}";

                                    if ($result->cname_found) {
                                        $message .= " CNAME found: {$result->cname_target}";
                                    } else {
                                        $message .= " CNAME not found for mta-sts.{$record->domain}";
                                    }

                                    Notification::make()
                                        ->title('MTA-STS check completed')
                                        ->body($message)
                                        ->success()
                                        ->send();
                                } else {
                                    $error = $result ? $result->error_message : 'Unknown error';
                                    Notification::make()
                                        ->title('MTA-STS check completed')
                                        ->body("No valid MTA-STS for {$record->domain}: {$error}")
                                        ->warning()
                                        ->send();
                                }
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('MTA-STS check failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])->icon(Heroicon::ArrowPath),
                
                ActionGroup::make([
                    Action::make('generateDKIM')
                        ->label('Generate DKIM Keys')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Generate DKIM Keys')
                        ->modalDescription('This will generate a new 2048-bit RSA key pair for DKIM signing. The existing key (if any) will be replaced.')
                        ->modalSubmitActionLabel('Generate')
                        ->action(function ($record, DKIMKeyService $dkimService, $livewire) {
                            try {
                                $dkim = $dkimService->generateKeys($record, 'default');
                                $dnsRecord = $dkim->getDnsRecord();

                                event(new \App\Events\DkimKeyGenerated(
                                    zone: $record->domain,
                                    name: $dnsRecord['name'],
                                    type: 'TXT',
                                    content: $dnsRecord['value'],
                                    ttl: 3600,
                                    operation: 'create'
                                ));

                                $record->unsetRelation('dkim');
                                $record->load('dkim');

                                $recordKey = json_encode($record->getKey());

                                $livewire->js(<<<JS
                                    setTimeout(() => {
                                        \$wire.mountTableAction('viewDkim', {$recordKey})
                                    }, 300);
                                JS);

                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error Generating DKIM Keys')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                        
                    Action::make('generateDmarc')
                        ->label('Generate DMARC')
                        ->icon('heroicon-o-plus-circle')
                        ->url(fn ($record) => DnsToolsResource::getUrl('generateDmarc', ['domain' => $record])),
                        
                    Action::make('generateSpf')
                        ->label('Generate SPF')
                        ->icon('heroicon-o-plus-circle')
                        ->url(fn ($record) => DnsToolsResource::getUrl('generateSpf', ['domain' => $record])),
                    
                    Action::make('createMtaSts')
                        ->label('Generate MTA-STS')
                        ->icon('heroicon-o-plus-circle')
                        ->color('success')
                        ->modalHeading(fn ($record) => "Generate MTA-STS Record for {$record->domain}")
                        ->modalSubheading(fn ($record) => "Configure MTA-STS policy for {$record->domain}")
                        ->modalWidth('lg')
                        ->form(function ($record) {
                            // Start with extension fields from MtaStsFormExtension
                            $extensionFields = [];

                            // Check if MtaStsFormExtension exists and has components
                            if (class_exists(MtaStsFormExtension::class) && method_exists(MtaStsFormExtension::class, 'components')) {
                                $extensionFields = MtaStsFormExtension::components($record);
                            }

                            // Base form fields
                            $baseFields = [
                                // Domain ID (hidden)
                                \Filament\Forms\Components\Hidden::make('domain_id')
                                    ->default(fn ($record) => $record->domain_id),

                                // Policy Type Dropdown
                                \Filament\Forms\Components\Select::make('policy_type')
                                    ->label('Policy Type')
                                    ->options([
                                        'none' => 'None (Disabled)',
                                        'testing' => 'Testing (Report Only)',
                                        'enforce' => 'Enforce (Strict)',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default('testing')
                                    ->placeholder('Select policy type')
                                    ->helperText('Choose the MTA-STS policy mode for this domain'),

                                // Max Age Input
                                \Filament\Forms\Components\TextInput::make('max_age')
                                    ->label('Max Age (seconds)')
                                    ->numeric()
                                    ->default(86400)
                                    ->minValue(1)
                                    ->maxValue(31557600)
                                    ->required()
                                    ->helperText('Default: 86400 (1 day). Max: 31557600 (1 year)')
                                    ->suffix('seconds')
                                    ->step(1),

                                // Generated ID (auto-generated)
                                \Filament\Forms\Components\Hidden::make('generated_id')
                                    ->default(Str::uuid()->toString()),

                                // Option to create CNAME
                                \Filament\Forms\Components\Checkbox::make('create_cname')
                                    ->label('Create CNAME record for mta-sts subdomain')
                                    ->helperText('Create a CNAME record for mta-sts.' . ($record->domain ?? 'domain') . ' pointing to the default MTA-STS provider')
                                    ->default(true),
                            ];

                            // Merge base fields with extension fields
                            return array_merge($baseFields, $extensionFields);
                        })
                        ->action(function (array $data, $record) {
                            try {
                                // Use updateOrCreate - this handles both creation and update
                                $mtaSts = MtaSts::updateOrCreate(
                                    ['domain_id' => $data['domain_id']], // Find by domain_id
                                    [ // Update or create with these values
                                        'policy_type' => $data['policy_type'],
                                        'max_age' => $data['max_age'],
                                        'generated_id' => $data['generated_id'],
                                    ]
                                );

                                // Check if this was a new record or an update
                                $wasRecentlyCreated = $mtaSts->wasRecentlyCreated;

                                // CREATE TXT RECORD (_mta-sts)
                                if (isset($data['update_dns']) && $data['update_dns']) {
                                    $dnsName = $data['dns_record_name'] ?? '_mta-sts';
                                    $dnsContent = $data['dns_record_value'] ?? "v=STSv1; id={$data['generated_id']}";
                                    $dnsTtl = $data['dns_ttl'] ?? 3600;

                                    event(new \App\Events\MtaStsRecordGenerated(
                                        mtaSts: $mtaSts,
                                        zone: $record->domain,
                                        type: "TXT",
                                        name: $dnsName,
                                        content: $dnsContent,
                                        ttl: $dnsTtl,
                                        operation: $wasRecentlyCreated ? 'create' : 'update'
                                    ));
                                }

                                // CREATE CNAME RECORD (mta-sts)
                                if (isset($data['create_cname']) && $data['create_cname']) {
                                    try {
                                        // Clear the cache first
                                        \VEximweb\Core\Data\Models\Setting::clearCache();

                                        // Get the setting value
                                        $cnameTarget = \VEximweb\Core\Data\Models\Setting::get('mta_sts_cname_default', '');

                                        \Log::debug('MTA-STS CNAME setting debug', [
                                            'domain' => $record->domain,
                                            'raw_target' => $cnameTarget,
                                            'is_empty' => empty($cnameTarget)
                                        ]);

                                        if (!empty($cnameTarget)) {
                                            // Format the CNAME target properly
                                            // Remove any trailing dot if exists, then add it back
                                            $cnameTarget = rtrim($cnameTarget, '.');

                                            // PowerDNS expects the target to be a fully qualified domain name
                                            // with a trailing dot to indicate it's an FQDN
                                            $formattedTarget = $cnameTarget . '.';

                                            \Log::info('Creating MTA-STS CNAME from createMtaSts action', [
                                                'domain' => $record->domain,
                                                'original_target' => $cnameTarget,
                                                'formatted_target' => $formattedTarget
                                            ]);

                                            event(new \App\Events\MtaStsRecordGenerated(
                                                mtaSts: null,
                                                zone: $record->domain,
                                                type: "CNAME",
                                                name: 'mta-sts',
                                                content: $formattedTarget, // Use the formatted target with trailing dot
                                                ttl: 3600,
                                                operation: 'create'
                                            ));

                                            \Log::info('MTA-STS CNAME event dispatched from createMtaSts action', [
                                                'domain' => $record->domain,
                                                'target' => $formattedTarget
                                            ]);

                                            Notification::make()
                                                ->title('CNAME Created')
                                                ->body("CNAME record for mta-sts.{$record->domain} will be created pointing to {$formattedTarget}")
                                                ->success()
                                                ->send();
                                        } else {
                                            \Log::warning('MTA-STS CNAME not created: default target not configured', [
                                                'domain' => $record->domain
                                            ]);

                                            Notification::make()
                                                ->title('CNAME Not Created')
                                                ->body('MTA-STS CNAME default target is not configured in settings (key: mta_sts_cname_default).')
                                                ->warning()
                                                ->send();
                                        }
                                    } catch (\Exception $e) {
                                        \Log::error('Failed to dispatch MTA-STS CNAME event from createMtaSts', [
                                            'domain' => $record->domain,
                                            'error' => $e->getMessage(),
                                            'trace' => $e->getTraceAsString()
                                        ]);

                                        Notification::make()
                                            ->title('CNAME Creation Failed')
                                            ->body('MTA-STS CNAME could not be created: ' . $e->getMessage())
                                            ->warning()
                                            ->send();
                                    }
                                }

                                // Run any save callbacks from extensions
                                if (class_exists(MtaStsFormExtension::class) && method_exists(MtaStsFormExtension::class, 'onSave')) {
                                    MtaStsFormExtension::onSave($mtaSts, $data);
                                }

                                $message = $wasRecentlyCreated 
                                    ? "MTA-STS record created for domain: {$record->domain}"
                                    : "MTA-STS record updated for domain: {$record->domain}";

                                if (isset($data['create_cname']) && $data['create_cname']) {
                                    $message .= " CNAME record for mta-sts.{$record->domain} also created.";
                                }

                                Notification::make()
                                    ->title($wasRecentlyCreated ? 'MTA-STS record created' : 'MTA-STS record updated')
                                    ->body($message)
                                    ->success()
                                    ->send();

                            } catch (\Exception $e) {
                                \Log::error('Error saving MTA-STS record', [
                                    'domain' => $record->domain,
                                    'error' => $e->getMessage()
                                ]);

                                Notification::make()
                                    ->title('Error saving MTA-STS record')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->modalSubmitActionLabel('Save MTA-STS Record')
                        ->modalCancelActionLabel('Cancel'),
                        
                    // DKIM Details Modal
                    Action::make('viewDkim')
                        ->label('DKIM Details')
                        ->icon('heroicon-o-eye')
                        ->modalHeading('DKIM Record Details')
                        ->modalSubheading(fn ($record) => $record->domain)
                        ->modalContent(function ($record) {
                            $dkim = $record->dkim;

                            return view('dns-tools::filament.modals.dkim-details', [
                                'domain' => $record->domain,
                                'dkim' => $dkim, // Pass the model directly
                                'record' => $record,
                            ]);
                        })
                        ->modalWidth('3xl')
                        ->modalActions([
                            Action::make('close')
                                ->label('Close')
                                ->close(),
                        ]),                  
                    
                    Action::make('viewDmarc')
                        ->label('DMARC Details')
                        ->icon('heroicon-o-eye')
                        ->modalHeading('DMARC Record Details')
                        ->modalSubheading(fn ($record) => $record->domain)
                        ->modalContent(fn ($record) => view(
                            'dns-tools::filament.modals.dmarc-details',
                            [
                                'domain' => $record->domain,
                                'dmarc' => $record->dmarcCheck,
                                'record' => $record,
                            ]
                        ))
                        ->modalWidth('3xl')
                        ->modalActions([
                            Action::make('checkAgain')
                                ->label('Check Again')
                                ->action(function ($record) {
                                    $service = app(\VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService::class);
                                    $service->checkDomain($record->domain, $record->domain_id);
                                    
                                    Notification::make()
                                        ->title('DMARC check completed')
                                        ->success()
                                        ->send();
                                }),
                            Action::make('close')
                                ->label('Close')
                                ->close(),
                        ]),
                        
                    // MTA-STS Details Modal
                    Action::make('viewMtaSts')
                        ->label('MTA-STS Details')
                        ->icon('heroicon-o-eye')
                        ->modalHeading('MTA-STS Details')
                        ->modalSubheading(fn ($record) => $record->domain)
                        ->modalContent(fn ($record) => view(
                            'dns-tools::filament.modals.mta-sts-details',
                            [
                                'domain' => $record->domain,
                                'mtaSts' => $record->mtaStsCheck,
                                'record' => $record,
                            ]
                        ))
                        ->modalWidth('3xl')
                        ->modalActions([
                            Action::make('checkAgain')
                                ->label('Check Again')
                                ->action(function ($record) {
                                    $service = app(\VEximweb\Plugin\DnsTools\Services\MtaStsService::class);
                                    $service->checkDomain($record->domain, $record->domain_id);
                                    
                                    Notification::make()
                                        ->title('MTA-STS check completed')
                                        ->success()
                                        ->send();
                                })
                                ->icon(Heroicon::ArrowPath),
                            Action::make('close')
                                ->label('Close')
                                ->close(),
                        ]),
                        
                    // SPF Details Modal
                    Action::make('viewSpf')
                        ->label('SPF Details')
                        ->icon('heroicon-o-eye')
                        ->modalHeading('SPF Record Details')
                        ->modalSubheading(fn ($record) => $record->domain)
                        ->modalContent(fn ($record) => view(
                            'dns-tools::filament.modals.spf-details',
                            [
                                'domain' => $record->domain,
                                'spf' => $record->spfCheck,
                                'record' => $record,
                            ]
                        ))
                        ->modalWidth('3xl')
                        ->modalActions([
                            Action::make('checkAgain')
                                ->label('Check Again')
                                ->action(function ($record) {
                                    $service = app(\VEximweb\Plugin\DnsTools\Services\SpfRecordService::class);
                                    $service->checkDomain($record->domain);
                                    
                                    Notification::make()
                                        ->title('SPF check completed')
                                        ->success()
                                        ->send();
                                })
                                ->icon(Heroicon::ArrowPath),
                            Action::make('close')
                                ->label('Close')
                                ->close(),
                        ]),
                ]),
            ]);
    }

}