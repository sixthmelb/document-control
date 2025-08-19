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
        Schema::table('documents', function (Blueprint $table) {
            // Add QR code columns if they don't exist
            if (!Schema::hasColumn('documents', 'qr_code_path')) {
                $table->string('qr_code_path')->nullable()->after('file_hash');
            }
            
            if (!Schema::hasColumn('documents', 'qr_code_token')) {
                $table->string('qr_code_token')->nullable()->after('qr_code_path');
            }
            
            // Add index for QR code token for faster lookups
            if (!Schema::hasColumn('documents', 'qr_code_token')) {
                $table->index('qr_code_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['qr_code_path', 'qr_code_token']);
        });
    }
};