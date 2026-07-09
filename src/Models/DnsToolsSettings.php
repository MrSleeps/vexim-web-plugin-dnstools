<?php

namespace VEximweb\Plugin\DnsTools\Models;

use VEximweb\Core\Data\Models\Setting as BaseSetting;
use Illuminate\Support\Collection;

class DnsToolsSettings extends BaseSetting
{
    
    public static function getPWASettings(): Collection
    {
        // Get all PWA settings from database
        $settings = self::where('key', 'LIKE', 'pwa_%')->get();
        // Convert to collection with key-value pairs
        $pwaSettings = collect();
		
        

        foreach ($settings as $setting) {
            $pwaSettings->put($setting->key, $setting->value);
        }
        
        // Merge with defaults to ensure all keys exist
        //$defaults = collect(self::$defaults);
        
        //return $defaults->merge($pwaSettings);
        return $pwaSettings;
    }
    

}