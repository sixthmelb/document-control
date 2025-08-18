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
            // Make file-related fields nullable for draft documents
            $table->string('original_filename')->nullable()->change();
            $table->string('file_path')->nullable()->change();
            $table->string('file_type')->nullable()->change();
            $table->unsignedBigInteger('file_size')->nullable()->change();
            $table->string('file_hash')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('original_filename')->nullable(false)->change();
            $table->string('file_path')->nullable(false)->change();
            $table->string('file_type')->nullable(false)->change();
            $table->unsignedBigInteger('file_size')->nullable(false)->change();
            $table->string('file_hash')->nullable(false)->change();
        });
    }
};