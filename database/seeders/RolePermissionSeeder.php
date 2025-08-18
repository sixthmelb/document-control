<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Enums\UserRole;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Document permissions
            'view_any_document',
            'view_own_document',
            'view_published_document',
            'create_document',
            'update_any_document',
            'update_own_document',
            'update_document',
            'delete_any_document',
            'delete_own_document',
            'review_document',
            'verify_document',
            'approve_document',
            'publish_document',
            
            // User management permissions
            'view_any_user',
            'view_own_user',
            'create_user',
            'update_any_user',
            'update_own_user',
            'delete_any_user',
            'manage_users',
            
            // Department and section permissions
            'view_any_department',
            'create_department',
            'update_any_department',
            'delete_any_department',
            'manage_departments',
            'view_any_section',
            'create_section',
            'update_any_section',
            'delete_any_section',
            'manage_sections',
            
            // Analytics and reporting
            'view_analytics',
            'view_department_analytics',
            'view_system_analytics',
            
            // System settings
            'manage_system_settings',
            'manage_notifications',
            'manage_qr_codes',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $this->createSuperAdminRole();
        $this->createAdminRole();
        $this->createUserRole();
    }

    /**
     * Create SuperAdmin role with all permissions.
     */
    private function createSuperAdminRole(): void
    {
        $superAdminRole = Role::create(['name' => UserRole::SUPERADMIN->value]);
        
        // Give all permissions to superadmin
        $superAdminRole->givePermissionTo(Permission::all());
    }

    /**
     * Create Admin role with department-level permissions.
     */
    private function createAdminRole(): void
    {
        $adminRole = Role::create(['name' => UserRole::ADMIN->value]);
        
        $adminPermissions = [
            // Document permissions
            'view_any_document',
            'view_published_document',
            'create_document',
            'update_document',
            'review_document',
            'verify_document',
            
            // Limited user management
            'view_any_user',
            'view_own_user',
            'update_own_user',
            
            // View departments and sections
            'view_any_department',
            'view_any_section',
            
            // Department analytics
            'view_department_analytics',
            
            // QR code management
            'manage_qr_codes',
        ];
        
        $adminRole->givePermissionTo($adminPermissions);
    }

    /**
     * Create User role with basic permissions.
     */
    private function createUserRole(): void
    {
        $userRole = Role::create(['name' => UserRole::USER->value]);
        
        $userPermissions = [
            // Document permissions
            'view_own_document',
            'view_published_document',
            'create_document',
            'update_own_document',
            'delete_own_document',
            
            // User profile
            'view_own_user',
            'update_own_user',
            
            // View departments and sections (for document creation)
            'view_any_department',
            'view_any_section',
        ];
        
        $userRole->givePermissionTo($userPermissions);
    }
}