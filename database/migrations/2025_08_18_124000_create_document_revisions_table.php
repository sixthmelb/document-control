<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\DocumentStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->string('version', 10)->comment('Version number like 1.0, 1.1, 2.0');
            $table->enum('status', array_column(DocumentStatus::cases(), 'value'));
            
            // File information for this revision
            $table->string('original_filename');
            $table->string('file_path')->comment('Path to the revision file');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash');
            
            // Revision details
            $table->text('revision_notes')->nullable()->comment('Notes about what changed');
            $table->json('changes_summary')->nullable()->comment('Summary of changes made');
            
            // Who made this revision
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Workflow timestamps for this revision
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            
            // Ensure version uniqueness per document
            $table->unique(['document_id', 'version']);
            
            // Indexes
            $table->index(['document_id', 'created_at']);
            $table->index(['status']);
            $table->index(['created_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
    }
};