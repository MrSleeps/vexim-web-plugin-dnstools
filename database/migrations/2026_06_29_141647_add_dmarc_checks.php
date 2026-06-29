<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vw_dmarc_checks', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique()->index();
            $table->unsignedBigInteger('domain_id')->nullable()->index();
            $table->text('record')->nullable();
            $table->string('policy')->nullable()->index();
            $table->string('subdomain_policy')->nullable();
            $table->string('adkim')->nullable();
            $table->string('aspf')->nullable();
            $table->json('rua')->nullable();
            $table->json('ruf')->nullable();
            $table->json('reporting')->nullable();
            $table->integer('percentage')->nullable();
            $table->integer('report_interval')->nullable();
            $table->string('np')->nullable();
            $table->string('psd')->nullable();
            $table->string('t')->nullable();
            $table->boolean('valid')->default(false)->index();
            $table->text('error_message')->nullable();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamps();
            
            // Add foreign key constraint if needed (optional)
            // $table->foreign('domain_id')->references('domain_id')->on('domains')->onDelete('cascade');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('vw_dmarc_checks');
    }
};