<?php

namespace VEximweb\Plugin\DnsTools\Filament\Resources\Dmarc\Pages;

use App\Settings\DmarcSettings;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use VEximweb\Core\Data\Repositories\SettingRepository;

class DmarcRecords extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static string $view = 'filament.pages.dmarc-records';
    
    protected static ?string $navigationGroup = 'Email';
    
    protected static ?int $navigationSort = 10;
    
    protected static ?string $title = 'DMARC Records';
    
    protected SettingRepository $settingRepository;
    
    public array $settings = [];
    
    public function __construct()
    {
        $this->settingRepository = app(SettingRepository::class);
        $this->settings = $this->getDmarcSettings();
    }
    
    public function getDmarcSettings(): array
    {
        $keys = [
            'dmarc_policy',
            'dmarc_subdomain_policy',
            'dmarc_adkim',
            'dmarc_aspf',
            'dmarc_percentage',
            'dmarc_report_interval',
            'dmarc_t',
            'dmarc_rua',
            'dmarc_ruf',
            'dmarc_reporting',
            'dmarc_np',
            'dmarc_psd',
            'dmarc_rua_localpart',
            'dmarc_ruf_localpart',
        ];
        
        $settings = [];
        foreach ($keys as $key) {
            $value = $this->settingRepository->get($key);
            $settings[$key] = $value;
        }
        
        return $settings;
    }
    
    public function getDefaultSettings(): array
    {
        return [
            'dmarc_policy' => 'none',
            'dmarc_subdomain_policy' => 'none',
            'dmarc_adkim' => 'relaxed',
            'dmarc_aspf' => 'relaxed',
            'dmarc_percentage' => 100,
            'dmarc_report_interval' => 86400,
            'dmarc_t' => 'n',
            'dmarc_rua' => [],
            'dmarc_ruf' => [],
            'dmarc_reporting' => ['all'],
            'dmarc_np' => '',
            'dmarc_psd' => '',
            'dmarc_rua_localpart' => 'dmarc',
            'dmarc_ruf_localpart' => 'dmarc',
        ];
    }
    
    public function getDmarcRecord(array $settings, string $domain): string
    {
        $parts = [];
        
        // Required: v (version)
        $parts[] = 'v=DMARC1';
        
        // Required: p (policy)
        $policy = $settings['dmarc_policy'] ?? 'none';
        $parts[] = "p=$policy";
        
        // pct (percentage)
        $percentage = $settings['dmarc_percentage'] ?? 100;
        if ($percentage !== 100) {
            $parts[] = "pct=$percentage";
        }
        
        // adkim (DKIM alignment)
        $adkim = $settings['dmarc_adkim'] ?? 'relaxed';
        if ($adkim !== 'relaxed') {
            $parts[] = "adkim=$adkim";
        }
        
        // aspf (SPF alignment)
        $aspf = $settings['dmarc_aspf'] ?? 'relaxed';
        if ($aspf !== 'relaxed') {
            $parts[] = "aspf=$aspf";
        }
        
        // rua (aggregate reports)
        $rua = $settings['dmarc_rua'] ?? [];
        if (!empty($rua)) {
            $ruaStr = implode(',', array_map(function ($email) {
                return "mailto:$email";
            }, $rua));
            $parts[] = "rua=$ruaStr";
        }
        
        // ruf (forensic reports)
        $ruf = $settings['dmarc_ruf'] ?? [];
        if (!empty($ruf)) {
            $rufStr = implode(',', array_map(function ($email) {
                return "mailto:$email";
            }, $ruf));
            $parts[] = "ruf=$rufStr";
        }
        
        // fo (failure reporting options)
        $reporting = $settings['dmarc_reporting'] ?? ['all'];
        if (!empty($reporting)) {
            $fo = implode('', array_map(function ($opt) {
                return $this->getFoValue($opt);
            }, $reporting));
            if (!empty($fo)) {
                $parts[] = "fo=$fo";
            }
        }
        
        // np (non-existent subdomain policy)
        $np = $settings['dmarc_np'] ?? '';
        if (!empty($np)) {
            $parts[] = "np=$np";
        }
        
        // sp (subdomain policy)
        $sp = $settings['dmarc_subdomain_policy'] ?? '';
        if (!empty($sp)) {
            $parts[] = "sp=$sp";
        }
        
        // psd (public suffix domain policy)
        $psd = $settings['dmarc_psd'] ?? '';
        if (!empty($psd)) {
            $parts[] = "psd=$psd";
        }
        
        // t (testing mode)
        $testing = $settings['dmarc_t'] ?? 'n';
        if ($testing === 'y') {
            $parts[] = 't=y';
        }
        
        // ri (report interval)
        $ri = $settings['dmarc_report_interval'] ?? 86400;
        if ($ri !== 86400) {
            $parts[] = "ri=$ri";
        }
        
        return implode('; ', $parts);
    }
    
    private function getFoValue(string $option): string
    {
        return match ($option) {
            'all' => '1',
            'any' => '1',
            'dkim' => 'd',
            'spf' => 's',
            'both' => '1',
            default => '1'
        };
    }
    
    public function getRuaDestinations(): array
    {
        $rua = $this->settings['dmarc_rua'] ?? [];
        $localpart = $this->settings['dmarc_rua_localpart'] ?? 'dmarc';
        
        return array_map(function ($email) use ($localpart) {
            return "mailto:$email";
        }, $rua);
    }
    
    public function getRufDestinations(): array
    {
        $ruf = $this->settings['dmarc_ruf'] ?? [];
        $localpart = $this->settings['dmarc_ruf_localpart'] ?? 'dmarc';
        
        return array_map(function ($email) use ($localpart) {
            return "mailto:$email";
        }, $ruf);
    }
    
    public static function getNavigationBadge(): ?string
    {
        return 'DMARC';
    }
}