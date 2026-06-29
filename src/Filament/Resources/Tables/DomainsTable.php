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
                
IconColumn::make('dmarc_status')
    ->label('DMARC')
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
            'not_checked' => 'heroicon-o-exclamation-triangle', // or 'heroicon-o-question-mark-circle'
        };
    })
    ->color(function ($state): string {
        return match($state) {
            'valid' => 'success',
            'invalid' => 'danger',
            'not_checked' => 'yellow',
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

                IconColumn::make('dmarccheck.valid')
                    ->label('DMARC')
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-check')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(function ($record): string {
                        $dmarc = $record->dmarcCheck;
                        
                        if (!$dmarc || $dmarc == NULL) {
                            return 'DMARC not checked yet';
                        }
                        
                        if ($dmarc->valid) {
                            return "DMARC valid (Policy: {$dmarc->getPolicyLabel()})";
                        } else {
                            return "DMARC invalid: {$dmarc->error_message}";
                        }
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
                    
                // DMARC Status Filter
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
            ])
  
            ->recordActions([
                Action::make('checkDmarc')
                    ->icon(Heroicon::ArrowPath)
                    ->label('')
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
          
                    
                //EditAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    EditAction::make(),
                    EditAction::make(),
                    Action::make('viewDmarc')
                        ->label('DMARC Details')
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
                ]),                
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                // Add bulk action to check DMARC for selected domains
                Action::make('checkSelectedDmarc')
                    ->label('Check DMARC for selected')
                    ->icon(Heroicon::ArrowPath)
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $service = app(\VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService::class);
                        $count = 0;
                        $valid = 0;
                        
                        foreach ($records as $record) {
                            $result = $service->checkDomain($record->domain, $record->domain_id);
                            $count++;
                            if ($result && $result->valid) {
                                $valid++;
                            }
                        }
                        
                        Notification::make()
                            ->title("DMARC check completed for {$count} domains")
                            ->body("{$valid} domains have valid DMARC records")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}