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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique()->comment('Format: AKM-IT-DEV-2025-08-0115');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('document_type')->default('general')->comment('SOP, Policy, Manual, etc');
            $table->enum('status', array_column(DocumentStatus::cases(), 'value'))->default(DocumentStatus::DRAFT->value);
            
            // File information
            $table->string('original_filename');
            $table->string('file_path')->comment('Path to the document file');
            $table->string('file_type')->comment('pdf, docx, etc');
            $table->unsignedBigInteger('file_size')->comment('File size in bytes');
            $table->string('file_hash')->comment('File hash for integrity check');
            
            // Document metadata
            $table->string('version', 10)->default('1.0');
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('tags')->nullable()->comment('Document tags for categorization');
            $table->json('metadata')->nullable()->comment('Additional document metadata');
            
            // Relationships
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->foreignId('current_reviewer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Workflow timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            
            // QR Code and security
            $table->string('qr_code_path')->nullable()->comment('Path to QR code image');
            $table->string('validation_token')->nullable()->comment('Token for QR code validation');
            $table->boolean('is_confidential')->default(false);
            
            // Statistics
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('view_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['department_id', 'status']);
            $table->index(['section_id', 'status']);
            $table->index(['creator_id']);
            $table->index(['document_type', 'status']);
            $table->index(['published_at']);
            $table->index(['validation_token']);
            $table->fullText(['title', 'description']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};