<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vw_spf_checks', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique()->index();
            $table->unsignedBigInteger('domain_id')->nullable()->index();
            $table->text('record')->nullable();
            $table->string('spf_version')->nullable()->default('v=spf1');
            $table->string('policy')->nullable()->index(); // -all, ~all, ?all, +all
            $table->integer('lookup_count')->default(0);
            $table->json('mechanisms')->nullable(); // Store all mechanisms as JSON
            $table->json('includes')->nullable(); // Include mechanisms
            $table->json('ip4')->nullable(); // IPv4 addresses
            $table->json('ip6')->nullable(); // IPv6 addresses
            $table->json('mx_domains')->nullable(); // MX mechanisms
            $table->json('a_domains')->nullable(); // A mechanisms
            $table->json('modifiers')->nullable(); // Redirect, Exp modifiers
            $table->boolean('has_ptr')->default(false);
            $table->boolean('has_exists')->default(false);
            $table->boolean('valid')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->json('validation_issues')->nullable(); // Store any validation issues
            $table->integer('mechanism_count')->default(0);
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamps();

            // Add foreign key constraint if needed
            // $table->foreign('domain_id')->references('domain_id')->on('domains')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vw_spf_checks');
    }
};
