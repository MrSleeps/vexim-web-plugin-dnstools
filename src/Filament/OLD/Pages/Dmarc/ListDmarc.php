<?php

namespace VEximweb\Plugin\DnsTools\Filament\Pages\Dmarc;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use VEximweb\Plugin\DnsTools\Filament\Resources\DmarcResource;
use VEximweb\Plugin\DnsTools\Models\DmarcCheck;
use VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService;
use VEximweb\Core\Data\Models\Domain;
use Illuminate\Database\Eloquent\Builder;

class ListDmarc extends ListRecords
{
    protected static string $resource = DmarcResource::class;

    protected function getTableQuery(): Builder
    {
        return Domain::where('enabled', true);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable()
                    ->size('lg')
                    ->weight('bold'),

                BadgeColumn::make('dmarc_status')
                    ->label('DMARC Status')
                    ->getStateUsing(function ($record) {
                        $dmarc = DmarcCheck::where('domain', $record->domain)->first();
                        if (!$dmarc) {
                            return 'Not Checked';
                        }
                        if (!$dmarc->valid) {
                            return 'No Record';
                        }
                        return $dmarc->getPolicyLabel();
                    })
                    ->colors([
                        'success' => 'Monitor',
                        'warning' => 'Quarantine',
                        'danger' => 'Reject',
                        'gray' => ['Not Checked', 'No Record'],
                    ]),

                BadgeColumn::make('dmarc_policy')
                    ->label('Policy')
                    ->getStateUsing(function ($record) {
                        $dmarc = DmarcCheck::where('domain', $record->domain)->first();
                        return $dmarc?->policy ?? '-';
                    })
                    ->colors([
                        'success' => 'none',
                        'warning' => 'quarantine',
                        'danger' => 'reject',
                        'gray' => '-',
                    ]),

                TextColumn::make('dmarc_last_checked')
                    ->label('Last Checked')
                    ->getStateUsing(function ($record) {
                        $dmarc = DmarcCheck::where('domain', $record->domain)->first();
                        return $dmarc?->last_checked_at;
                    })
                    ->since()
                    ->toggleable(),

                TextColumn::make('exim_users_count')
                    ->label('Accounts')
                    ->counts('eximUsers')
                    ->badge()
                    ->color('info'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('dmarc_status')
                    ->label('DMARC Status')
                    ->options([
                        'has_record' => 'Has DMARC Record',
                        'no_record' => 'No DMARC Record',
                        'not_checked' => 'Not Checked',
                    ])
                    ->query(function ($query, $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return match($data['value']) {
                            'has_record' => $query->whereIn('domain', 
                                DmarcCheck::where('valid', true)->pluck('domain')->toArray()
                            ),
                            'no_record' => $query->whereIn('domain', 
                                DmarcCheck::where('valid', false)->pluck('domain')->toArray()
                            ),
                            'not_checked' => $query->whereNotIn('domain', 
                                DmarcCheck::pluck('domain')->toArray()
                            ),
                            default => $query,
                        };
                    }),

                \Filament\Tables\Filters\SelectFilter::make('policy')
                    ->label('Policy')
                    ->options([
                        'none' => 'Monitor',
                        'quarantine' => 'Quarantine',
                        'reject' => 'Reject',
                    ])
                    ->query(function ($query, $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        $domains = DmarcCheck::where('policy', $data['value'])
                            ->where('valid', true)
                            ->pluck('domain')
                            ->toArray();
                        return $query->whereIn('domain', $domains);
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('View DMARC')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn ($record): string => DmarcResource::getUrl('view', ['record' => $record])),

                Action::make('generate')
                    ->label('Generate Record')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->url(fn ($record): string => DmarcResource::getUrl('generate', ['record' => $record])),

                Action::make('check_dns')
                    ->label('Check DNS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function ($record) {
                        $service = app(DmarcCheckService::class);
                        $result = $service->checkDomain($record->domain, $record->domain_id);
                        
                        if ($result && $result->valid) {
                            Notification::make()
                                ->success()
                                ->title('DMARC Record Found')
                                ->body("Policy: {$result->getPolicyLabel()}")
                                ->send();
                        } else {
                            Notification::make()
                                ->warning()
                                ->title('No DMARC Record Found')
                                ->body($result?->error_message ?? 'No DMARC record found in DNS')
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    Action::make('check_all_dmarc')
                        ->label('Check DMARC for Selected')
                        ->icon('heroicon-o-arrow-path')
                        ->action(function ($records) {
                            $service = app(DmarcCheckService::class);
                            $valid = 0;
                            
                            foreach ($records as $record) {
                                $result = $service->checkDomain($record->domain, $record->domain_id);
                                if ($result && $result->valid) {
                                    $valid++;
                                }
                            }
                            
                            Notification::make()
                                ->success()
                                ->title('DMARC Check Complete')
                                ->body("Checked {$records->count()} domains, {$valid} have valid DMARC records")
                                ->send();
                        }),
                ]),
            ]);
    }
}
