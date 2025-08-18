<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('Starting database seeding...');
        
        // Set company code in config for document numbering
        config(['app.company_code' => 'AKM']);
        
        $this->command->info('1. Creating roles and permissions...');
        $this->call(RolePermissionSeeder::class);
        
        $this->command->info('2. Creating departments and sections...');
        $this->call(DepartmentSeeder::class);
        
        $this->command->info('3. Creating users...');
        $this->call(UserSeeder::class);
        
        $this->command->info('4. Creating sample documents...');
        $this->call(DocumentSeeder::class);
        
        $this->command->info('Database seeding completed successfully!');
        $this->command->info('');
        $this->command->info('=== LOGIN CREDENTIALS ===');
        $this->command->info('Super Administrator:');
        $this->command->info('  Email: admin@akm.com');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('IT Administrator:');
        $this->command->info('  Email: it.admin@akm.com');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('HR Administrator:');
        $this->command->info('  Email: hr.admin@akm.com');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('Regular Users:');
        $this->command->info('  Email: john.doe@akm.com (and others)');
        $this->command->info('  Password: password123');
        $this->command->info('');
        $this->command->info('=== NEXT STEPS ===');
        $this->command->info('1. Run: php artisan serve');
        $this->command->info('2. Visit: http://localhost:8000 (Public)');
        $this->command->info('3. Visit: http://localhost:8000/admin (Admin Panel)');
        $this->command->info('4. Visit: http://localhost:8000/login (User Login)');
        $this->command->info('');
        $this->command->info('=== FEATURES AVAILABLE ===');
        $this->command->info('✓ Document upload and management');
        $this->command->info('✓ Document workflow (Draft → Submit → Review → Approve → Publish)');
        $this->command->info('✓ QR code generation and validation');
        $this->command->info('✓ Public document portal');
        $this->command->info('✓ Role-based access control');
        $this->command->info('✓ Email notifications');
        $this->command->info('✓ Document search and filtering');
        $this->command->info('✓ Audit trail and analytics');
    }
}