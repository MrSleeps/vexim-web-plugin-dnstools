<?php

namespace VEximweb\Plugin\DnsTools\Filament\Pages\Dmarc;

use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use VEximweb\Plugin\DnsTools\Filament\Resources\DmarcResource;
use VEximweb\Plugin\DnsTools\Models\DmarcCheck;
use VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService;

class ViewDmarc extends ViewRecord
{
    protected static string $resource = DmarcResource::class;

    public ?DmarcCheck $dmarc = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Get the DMARC record for this domain
        $this->dmarc = DmarcCheck::where('domain', $this->record->domain)->first();

        // If no cache or invalid, check now
        if (!$this->dmarc || !$this->dmarc->valid) {
            $service = app(DmarcCheckService::class);
            $this->dmarc = $service->checkDomain($this->record->domain, $this->record->domain_id);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check_dns')
                ->label('Check DNS Again')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () {
                    $service = app(DmarcCheckService::class);
                    $this->dmarc = $service->checkDomain($this->record->domain, $this->record->domain_id);
                    
                    Notification::make()
                        ->success()
                        ->title('DMARC Record Updated')
                        ->body($this->dmarc?->valid ? 'Record found and updated' : 'No valid record found')
                        ->send();

                    $this->refreshFormData();
                }),

            Action::make('generate')
                ->label('Generate New Record')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->url(DmarcResource::getUrl('generate', ['record' => $this->record])),

            Action::make('back')
                ->label('Back to List')
                ->icon('heroicon-o-arrow-left')
                ->url(DmarcResource::getUrl('index')),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'dmarc' => $this->dmarc,
        ];
    }

    public function getTitle(): string
    {
        return "DMARC Record for {$this->record->domain}";
    }
}
