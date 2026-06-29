<?php

namespace  VEximweb\Plugin\DnsTools\Filament\Resources\Pages;

use VEximweb\Plugin\DnsTools\Filament\Resources\DnsToolsResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDomains extends ListRecords
{
    protected static string $resource = DnsToolsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            
        ];
    }
}
