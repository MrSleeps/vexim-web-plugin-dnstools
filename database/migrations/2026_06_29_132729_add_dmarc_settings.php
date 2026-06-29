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
        // Using the setting repository to add DMARC settings
        $repository = app(SettingRepository::class);

        // DMARC core settings
        $settings = [
            'dmarc_policy' => [
                'value' => 'none',
                'type' => 'string',
                'description' => 'DMARC policy: none, quarantine, or reject',
            ],
            'dmarc_subdomain_policy' => [
                'value' => null,
                'type' => 'string',
                'description' => 'DMARC subdomain policy: none, quarantine, or reject',
            ],
            'dmarc_adkim' => [
                'value' => 'relaxed',
                'type' => 'string',
                'description' => 'DKIM alignment: relaxed or strict',
            ],
            'dmarc_aspf' => [
                'value' => 'relaxed',
                'type' => 'string',
                'description' => 'SPF alignment: relaxed or strict',
            ],
            'dmarc_percentage' => [
                'value' => 100,
                'type' => 'integer',
                'description' => 'Percentage of messages to apply policy to (0-100)',
            ],
            'dmarc_report_interval' => [
                'value' => 86400,
                'type' => 'integer',
                'description' => 'Reporting interval in seconds (default 24 hours)',
            ],
            'dmarc_t' => [
                'value' => 'n',
                'type' => 'string',
                'description' => 'Testing mode: y (yes) or n (no)',
            ],

            // Reporting addresses
            'dmarc_rua' => [
                'value' => [],
                'type' => 'json',
                'description' => 'Aggregate report email addresses',
            ],
            'dmarc_ruf' => [
                'value' => [],
                'type' => 'json',
                'description' => 'Forensic report email addresses',
            ],
            'dmarc_reporting' => [
                'value' => ['all'],
                'type' => 'json',
                'description' => 'Failure reporting options (fo)',
            ],

            // Advanced settings
            'dmarc_np' => [
                'value' => null,
                'type' => 'string',
                'description' => 'Non-existent subdomain policy',
            ],
            'dmarc_psd' => [
                'value' => null,
                'type' => 'string',
                'description' => 'Public suffix domain policy: y, n, or u',
            ],

            // Email localparts (overrides config values)
            'dmarc_rua_localpart' => [
                'value' => env('DMARC_RUA_LOCALPART', 'dmarc'),
                'type' => 'string',
                'description' => 'Custom local part for RUA reports',
            ],
            'dmarc_ruf_localpart' => [
                'value' => env('DMARC_RUF_LOCALPART', 'dmarc'),
                'type' => 'string',
                'description' => 'Custom local part for RUF reports',
            ],
        ];

        foreach ($settings as $key => $config) {
            // Check if setting already exists
            if (!$repository->has($key)) {
                $repository->set($key, $config['value'], $config['type'], $config['description']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $repository = app(SettingRepository::class);

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

        $repository->deleteMultiple($keys);
    }
};
