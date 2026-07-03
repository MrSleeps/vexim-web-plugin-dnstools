<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vw_mta_sts_checks', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->unsignedBigInteger('domain_id')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable(); // <-- Add this line
            
            // DNS record data
            $table->boolean('dns_valid')->default(false);
            $table->text('dns_policy')->nullable();
            $table->string('dns_mode')->nullable();
            $table->string('dns_mx')->nullable();
            $table->integer('dns_max_age')->nullable();
            $table->timestamp('dns_expires_at')->nullable();
            
            // Policy file data
            $table->boolean('policy_valid')->default(false);
            $table->json('policy_data')->nullable();
            $table->timestamp('policy_fetched_at')->nullable();
            
            // MX validation
            $table->boolean('mx_mismatch')->default(false);
            $table->json('mx_validation_details')->nullable();
            
            // Metadata
            $table->text('error_message')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('domain');
            $table->index('domain_id');
            $table->index('dns_valid');
            $table->index('policy_valid');
            $table->index('mx_mismatch');
            $table->index('checked_at');
            $table->index('next_check_at');
            $table->index('dns_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vw_mta_sts_checks');
    }
};