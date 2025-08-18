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
        Schema::create('document_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')->comment('Null for anonymous downloads');
            
            // Download details
            $table->string('download_type')->default('view')->comment('view, download, print');
            $table->string('user_type')->default('guest')->comment('authenticated, guest, api');
            $table->string('session_id')->nullable()->comment('Session identifier for anonymous users');
            
            // Request information
            $table->string('ip_address');
            $table->string('user_agent')->nullable();
            $table->string('referer')->nullable();
            $table->json('request_headers')->nullable();
            
            // Access details
            $table->string('access_method')->default('web')->comment('web, api, qr_code, direct_link');
            $table->string('device_type')->nullable()->comment('mobile, desktop, tablet');
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            
            // Geographic information (optional)
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();
            
            // Additional metadata
            $table->boolean('is_successful')->default(true)->comment('Whether download was successful');
            $table->text('error_message')->nullable()->comment('Error message if download failed');
            $table->unsignedInteger('bytes_served')->nullable()->comment('Number of bytes served');
            $table->unsignedInteger('duration_ms')->nullable()->comment('Request duration in milliseconds');
            
            $table->timestamps();
            
            // Indexes for analytics and performance
            $table->index(['document_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['download_type', 'created_at']);
            $table->index(['access_method', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['is_successful']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_downloads');
    }
};