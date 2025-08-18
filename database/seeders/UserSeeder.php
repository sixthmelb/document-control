<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Department;
use App\Models\Section;
use App\Enums\UserRole;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get departments and sections for assignment
        $itDepartment = Department::where('code', 'IT')->first();
        $hrDepartment = Department::where('code', 'HR')->first();
        $finDepartment = Department::where('code', 'FIN')->first();
        
        $itDevSection = Section::where('code', 'DEV')->where('department_id', $itDepartment->id)->first();
        $itSupportSection = Section::where('code', 'SUPPORT')->where('department_id', $itDepartment->id)->first();
        $hrRecruitSection = Section::where('code', 'RECRUIT')->where('department_id', $hrDepartment->id)->first();
        $finAccountSection = Section::where('code', 'ACCOUNT')->where('department_id', $finDepartment->id)->first();

        // Create Super Administrator
        $superAdmin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@akm.com',
            'password' => Hash::make('password123'),
            'employee_id' => 'EMP001',
            'role' => UserRole::SUPERADMIN,
            'department_id' => $itDepartment->id,
            'section_id' => $itDevSection->id,
            'phone' => '+62-812-3456-7890',
            'address' => 'Jakarta, Indonesia',
            'is_active' => true,
            'email_verified_at' => now(),
            'preferences' => [
                'email_notifications' => [
                    'submitted' => true,
                    'revision_requested' => true,
                    'verified' => true,
                    'approved' => true,
                    'published' => true,
                    'rejected' => true,
                    'expiring' => true,
                    'overdue_reviews' => true,
                ],
                'dashboard_layout' => 'detailed',
                'items_per_page' => 25,
            ],
        ]);
        $superAdmin->assignRole(UserRole::SUPERADMIN->value);

        // Create IT Administrator
        $itAdmin = User::create([
            'name' => 'IT Administrator',
            'email' => 'it.admin@akm.com',
            'password' => Hash::make('password123'),
            'employee_id' => 'EMP002',
            'role' => UserRole::ADMIN,
            'department_id' => $itDepartment->id,
            'section_id' => $itDevSection->id,
            'phone' => '+62-812-3456-7891',
            'address' => 'Surabaya, Indonesia',
            'is_active' => true,
            'email_verified_at' => now(),
            'preferences' => [
                'email_notifications' => [
                    'submitted' => true,
                    'revision_requested' => true,
                    'verified' => false,
                    'approved' => false,
                    'published' => true,
                    'rejected' => true,
                    'expiring' => true,
                    'overdue_reviews' => true,
                ],
                'dashboard_layout' => 'compact',
                'items_per_page' => 20,
            ],
        ]);
        $itAdmin->assignRole(UserRole::ADMIN->value);

        // Create HR Administrator
        $hrAdmin = User::create([
            'name' => 'HR Administrator',
            'email' => 'hr.admin@akm.com',
            'password' => Hash::make('password123'),
            'employee_id' => 'EMP003',
            'role' => UserRole::ADMIN,
            'department_id' => $hrDepartment->id,
            'section_id' => $hrRecruitSection->id,
            'phone' => '+62-812-3456-7892',
            'address' => 'Bandung, Indonesia',
            'is_active' => true,
            'email_verified_at' => now(),
            'preferences' => [
                'email_notifications' => [
                    'submitted' => true,
                    'revision_requested' => true,
                    'verified' => false,
                    'approved' => false,
                    'published' => true,
                    'rejected' => true,
                    'expiring' => true,
                    'overdue_reviews' => true,
                ],
                'dashboard_layout' => 'compact',
                'items_per_page' => 15,
            ],
        ]);
        $hrAdmin->assignRole(UserRole::ADMIN->value);

        // Create Regular Users
        $users = [
            [
                'name' => 'John Doe',
                'email' => 'john.doe@akm.com',
                'employee_id' => 'EMP101',
                'department_id' => $itDepartment->id,
                'section_id' => $itDevSection->id,
                'phone' => '+62-812-1111-1111',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane.smith@akm.com',
                'employee_id' => 'EMP102',
                'department_id' => $itDepartment->id,
                'section_id' => $itSupportSection->id,
                'phone' => '+62-812-2222-2222',
            ],
            [
                'name' => 'Mike Johnson',
                'email' => 'mike.johnson@akm.com',
                'employee_id' => 'EMP103',
                'department_id' => $hrDepartment->id,
                'section_id' => $hrRecruitSection->id,
                'phone' => '+62-812-3333-3333',
            ],
            [
                'name' => 'Sarah Wilson',
                'email' => 'sarah.wilson@akm.com',
                'employee_id' => 'EMP104',
                'department_id' => $finDepartment->id,
                'section_id' => $finAccountSection->id,
                'phone' => '+62-812-4444-4444',
            ],
            [
                'name' => 'David Brown',
                'email' => 'david.brown@akm.com',
                'employee_id' => 'EMP105',
                'department_id' => $itDepartment->id,
                'section_id' => $itDevSection->id,
                'phone' => '+62-812-5555-5555',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create(array_merge($userData, [
                'password' => Hash::make('password123'),
                'role' => UserRole::USER,
                'address' => 'Indonesia',
                'is_active' => true,
                'email_verified_at' => now(),
                'preferences' => [
                    'email_notifications' => [
                        'submitted' => false,
                        'revision_requested' => true,
                        'verified' => false,
                        'approved' => true,
                        'published' => true,
                        'rejected' => true,
                        'expiring' => false,
                        'overdue_reviews' => false,
                    ],
                    'dashboard_layout' => 'simple',
                    'items_per_page' => 10,
                ],
            ]));
            
            $user->assignRole(UserRole::USER->value);
        }

        // Create some inactive users for testing
        $inactiveUser = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@akm.com',
            'password' => Hash::make('password123'),
            'employee_id' => 'EMP999',
            'role' => UserRole::USER,
            'department_id' => $itDepartment->id,
            'section_id' => $itDevSection->id,
            'phone' => '+62-812-9999-9999',
            'address' => 'Indonesia',
            'is_active' => false,
            'email_verified_at' => now(),
        ]);
        $inactiveUser->assignRole(UserRole::USER->value);

        $this->command->info('Created users:');
        $this->command->info('- Super Administrator: admin@akm.com (password: password123)');
        $this->command->info('- IT Administrator: it.admin@akm.com (password: password123)');
        $this->command->info('- HR Administrator: hr.admin@akm.com (password: password123)');
        $this->command->info('- 5 Regular Users with password: password123');
        $this->command->info('- 1 Inactive User for testing');
    }
}