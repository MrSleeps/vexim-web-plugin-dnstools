<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update all settings with keys starting with 'dmarc_' to have category 'dmarc'
        DB::table('vw_settings')
            ->where('key', 'like', 'dmarc_%')
            ->update([
                'category' => 'dmarc',
                'updated_at' => now(),
            ]);
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
