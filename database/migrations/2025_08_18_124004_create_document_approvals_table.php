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
        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_revision_id')->nullable()->constrained()->onDelete('set null');
            
            // Approval details
            $table->enum('previous_status', array_column(DocumentStatus::cases(), 'value'));
            $table->enum('new_status', array_column(DocumentStatus::cases(), 'value'));
            $table->string('action')->comment('submitted, reviewed, approved, rejected, etc');
            
            // Who performed the action
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('user_role')->comment('Role at the time of action');
            
            // Action details
            $table->text('comments')->nullable()->comment('Comments from reviewer/approver');
            $table->text('revision_notes')->nullable()->comment('What needs to be revised');
            $table->json('checklist')->nullable()->comment('Review checklist if applicable');
            
            // Additional metadata
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('additional_data')->nullable()->comment('Any additional action data');
            
            $table->timestamps();
            
            // Indexes for audit queries
            $table->index(['document_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['new_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
    }
};