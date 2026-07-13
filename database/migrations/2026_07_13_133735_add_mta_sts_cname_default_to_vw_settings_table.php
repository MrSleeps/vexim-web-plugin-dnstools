<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use VEximweb\Core\Data\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Check if the setting already exists to avoid duplicates
            $existingSetting = Setting::where('key', 'mta_sts_cname_default')->first();
            
            if (!$existingSetting) {
                Setting::create([
                    'key' => 'mta_sts_cname_default',
                    'value' => '', // Empty by default - user must configure
                    'type' => 'string',
                    'description' => 'Default CNAME target for MTA-STS (mta-sts.domain.com). Example: mta-sts.cloudflare.com or sts.your-email-provider.com',
                ]);
                
                Log::info('MTA-STS CNAME default setting created successfully');
            } else {
                Log::info('MTA-STS CNAME default setting already exists, skipping creation');
            }
        } catch (\Exception $e) {
            Log::error('Failed to create MTA-STS CNAME default setting', [
                'error' => $e->getMessage()
            ]);
            
            // Re-throw the exception to make the migration fail
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Remove the setting when rolling back
            $deleted = Setting::where('key', 'mta_sts_cname_default')->delete();
            
            if ($deleted) {
                Log::info('MTA-STS CNAME default setting removed successfully');
            } else {
                Log::info('MTA-STS CNAME default setting not found, nothing to remove');
            }
        } catch (\Exception $e) {
            Log::error('Failed to remove MTA-STS CNAME default setting', [
                'error' => $e->getMessage()
            ]);
            
            // Re-throw the exception to make the migration fail
            throw $e;
        }
    }
};
