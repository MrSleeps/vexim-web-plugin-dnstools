<?php

use Illuminate\Database\Migrations\Migration;
use VEximweb\Core\Data\Repositories\SettingRepository;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $repository = app(SettingRepository::class);

        // Descriptions to add or update
        $descriptions = [
            'dmarc_policy' => 'DMARC policy: none, quarantine, or reject',
            'dmarc_subdomain_policy' => 'DMARC subdomain policy: none, quarantine, or reject',
            'dmarc_adkim' => 'DKIM alignment: relaxed or strict',
            'dmarc_aspf' => 'SPF alignment: relaxed or strict',
            'dmarc_percentage' => 'Percentage of messages to apply policy to (0-100)',
            'dmarc_report_interval' => 'Reporting interval in seconds (default 24 hours)',
            'dmarc_t' => 'Testing mode: y (yes) or n (no)',
            'dmarc_rua' => 'Aggregate report email addresses',
            'dmarc_ruf' => 'Forensic report email addresses',
            'dmarc_reporting' => 'Failure reporting options (fo) - valid values: all, 0, 1, d, s',
            'dmarc_np' => 'Non-existent subdomain policy: none, quarantine, or reject',
            'dmarc_psd' => 'Public suffix domain policy: y (yes), n (no), or u (user-specified)',
            'dmarc_rua_localpart' => 'Custom local part for aggregate (RUA) report email addresses',
            'dmarc_ruf_localpart' => 'Custom local part for forensic (RUF) report email addresses',
        ];

        // Default values for each setting
        $defaults = [
            'dmarc_policy' => ['value' => 'none', 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_subdomain_policy' => ['value' => null, 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_adkim' => ['value' => 'relaxed', 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_aspf' => ['value' => 'relaxed', 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_percentage' => ['value' => 100, 'type' => 'integer', 'category' => 'dmarc'],
            'dmarc_report_interval' => ['value' => 86400, 'type' => 'integer', 'category' => 'dmarc'],
            'dmarc_t' => ['value' => 'n', 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_rua' => ['value' => [], 'type' => 'json', 'category' => 'dmarc'],
            'dmarc_ruf' => ['value' => [], 'type' => 'json', 'category' => 'dmarc'],
            'dmarc_reporting' => ['value' => ['all'], 'type' => 'json', 'category' => 'dmarc'],
            'dmarc_np' => ['value' => null, 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_psd' => ['value' => null, 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_rua_localpart' => ['value' => env('DMARC_RUA_LOCALPART', 'dmarc'), 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_ruf_localpart' => ['value' => env('DMARC_RUF_LOCALPART', 'dmarc'), 'type' => 'string', 'category' => 'dmarc'],
        ];

        foreach ($descriptions as $key => $description) {
            if ($repository->has($key)) {
                $currentValue = $repository->get($key);
                $type = $defaults[$key]['type'] ?? 'string';
                $category = $defaults[$key]['category'] ?? 'dmarc';
                
                $repository->set(
                    $key,
                    $currentValue,
                    $type,
                    $description,
                    $category
                );
            } else {
                if (isset($defaults[$key])) {
                    $repository->set(
                        $key,
                        $defaults[$key]['value'],
                        $defaults[$key]['type'],
                        $description,
                        $defaults[$key]['category']
                    );
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $repository = app(SettingRepository::class);

        // Rollback descriptions to empty strings
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

        $defaults = [
            'dmarc_policy' => ['value' => 'none', 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_subdomain_policy' => ['value' => null, 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_adkim' => ['value' => 'relaxed', 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_aspf' => ['value' => 'relaxed', 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_percentage' => ['value' => 100, 'type' => 'integer', 'category' => 'dmarc'],
            'dmarc_report_interval' => ['value' => 86400, 'type' => 'integer', 'category' => 'dmarc'],
            'dmarc_t' => ['value' => 'n', 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_rua' => ['value' => [], 'type' => 'json', 'category' => 'dmarc'],
            'dmarc_ruf' => ['value' => [], 'type' => 'json', 'category' => 'dmarc'],
            'dmarc_reporting' => ['value' => ['all'], 'type' => 'json', 'category' => 'dmarc'],
            'dmarc_np' => ['value' => null, 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_psd' => ['value' => null, 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_rua_localpart' => ['value' => env('DMARC_RUA_LOCALPART', 'dmarc'), 'type' => 'string', 'category' => 'dmarc'],
            'dmarc_ruf_localpart' => ['value' => env('DMARC_RUF_LOCALPART', 'dmarc'), 'type' => 'string', 'category' => 'dmarc'],
        ];

        foreach ($keys as $key) {
            if ($repository->has($key)) {
                $currentValue = $repository->get($key);
                $type = $defaults[$key]['type'] ?? 'string';
                $category = $defaults[$key]['category'] ?? 'dmarc';
                
                $repository->set(
                    $key,
                    $currentValue,
                    $type,
                    '', // Empty description on rollback
                    $category
                );
            }
        }
    }
};