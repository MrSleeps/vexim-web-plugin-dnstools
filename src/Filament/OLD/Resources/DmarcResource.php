<?php

namespace VEximweb\Plugin\DnsTools\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use VEximweb\Core\Data\Models\Domain;
use VEximweb\Plugin\DnsTools\Models\DmarcCheck;
use VEximweb\Plugin\DnsTools\Dmarc\Services\DmarcCheckService;
use VEximweb\Plugin\DnsTools\Dmarc\DmarcRecord;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcPolicy;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcAlignment;
use VEximweb\Plugin\DnsTools\Dmarc\Enums\DmarcReporting;
use BackedEnum;
use VEximweb\Plugin\DnsTools\Filament\Pages\Dmarc\ListDmarc;
use VEximweb\Plugin\DnsTools\Filament\Pages\Dmarc\ViewDmarc;
use VEximweb\Plugin\DnsTools\Filament\Pages\Dmarc\GenerateDmarc;

class DmarcResource extends Resource
{
    protected static ?string $model = Domain::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static string|\UnitEnum|null $navigationGroup = 'DNS Tools';
    protected static ?string $navigationLabel = 'DMARC Management';
    protected static ?string $pluralLabel = 'DMARC Management';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'dnstools/dmarc';

    public static function getModel(): string
    {
        return Domain::class;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDmarc::route('/'),
            'view' => ViewDmarc::route('/{record}/view'),
            'generate' => GenerateDmarc::route('/{record}/generate'),
        ];
    }
}