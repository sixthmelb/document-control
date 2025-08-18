<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\UserRole;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Add additional columns to users table WITHOUT foreign key constraints
        Schema::table('users', function (Blueprint $table) {
            $table->after('email_verified_at', function (Blueprint $table) {
                $table->string('employee_id')->nullable()->unique()->comment('Employee identification number');
                $table->enum('role', array_column(UserRole::cases(), 'value'))->default(UserRole::USER->value);
                $table->unsignedBigInteger('department_id')->nullable(); // No foreign key yet
                $table->unsignedBigInteger('section_id')->nullable();    // No foreign key yet
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->json('preferences')->nullable()->comment('User preferences and settings');
            });
        });

        // Add indexes for better performance
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'is_active']);
            $table->index(['department_id', 'is_active']);
            $table->index(['section_id', 'is_active']);
            $table->index(['employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};