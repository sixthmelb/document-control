<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Section;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'code' => 'IT',
                'name' => 'Information Technology',
                'description' => 'Responsible for managing technology infrastructure, software development, and IT support services.',
                'sections' => [
                    ['code' => 'DEV', 'name' => 'Development', 'description' => 'Software development and programming'],
                    ['code' => 'INFRA', 'name' => 'Infrastructure', 'description' => 'Network and server management'],
                    ['code' => 'SUPPORT', 'name' => 'Technical Support', 'description' => 'User support and help desk services'],
                    ['code' => 'SECURITY', 'name' => 'Information Security', 'description' => 'Cybersecurity and data protection'],
                ]
            ],
            [
                'code' => 'HR',
                'name' => 'Human Resources',
                'description' => 'Manages employee relations, recruitment, training, and organizational development.',
                'sections' => [
                    ['code' => 'RECRUIT', 'name' => 'Recruitment', 'description' => 'Talent acquisition and hiring'],
                    ['code' => 'TRAINING', 'name' => 'Training & Development', 'description' => 'Employee training and skill development'],
                    ['code' => 'PAYROLL', 'name' => 'Payroll', 'description' => 'Salary and benefits administration'],
                    ['code' => 'EMPLOYEE', 'name' => 'Employee Relations', 'description' => 'Employee engagement and relations'],
                ]
            ],
            [
                'code' => 'FIN',
                'name' => 'Finance',
                'description' => 'Handles financial planning, accounting, budgeting, and financial reporting.',
                'sections' => [
                    ['code' => 'ACCOUNT', 'name' => 'Accounting', 'description' => 'Financial record keeping and reporting'],
                    ['code' => 'BUDGET', 'name' => 'Budget Planning', 'description' => 'Budget preparation and monitoring'],
                    ['code' => 'AUDIT', 'name' => 'Internal Audit', 'description' => 'Internal auditing and compliance'],
                    ['code' => 'TREASURY', 'name' => 'Treasury', 'description' => 'Cash management and investments'],
                ]
            ],
            [
                'code' => 'OPS',
                'name' => 'Operations',
                'description' => 'Oversees daily operations, process improvement, and operational efficiency.',
                'sections' => [
                    ['code' => 'PROCESS', 'name' => 'Process Management', 'description' => 'Business process optimization'],
                    ['code' => 'QUALITY', 'name' => 'Quality Assurance', 'description' => 'Quality control and assurance'],
                    ['code' => 'LOGISTICS', 'name' => 'Logistics', 'description' => 'Supply chain and logistics management'],
                    ['code' => 'FACILITY', 'name' => 'Facilities', 'description' => 'Facility management and maintenance'],
                ]
            ],
            [
                'code' => 'MARKETING',
                'name' => 'Marketing',
                'description' => 'Develops marketing strategies, brand management, and customer engagement initiatives.',
                'sections' => [
                    ['code' => 'DIGITAL', 'name' => 'Digital Marketing', 'description' => 'Online marketing and social media'],
                    ['code' => 'BRAND', 'name' => 'Brand Management', 'description' => 'Brand strategy and management'],
                    ['code' => 'CONTENT', 'name' => 'Content Creation', 'description' => 'Marketing content and materials'],
                    ['code' => 'EVENTS', 'name' => 'Events & PR', 'description' => 'Event management and public relations'],
                ]
            ],
            [
                'code' => 'SALES',
                'name' => 'Sales',
                'description' => 'Manages sales activities, customer relationships, and revenue generation.',
                'sections' => [
                    ['code' => 'DIRECT', 'name' => 'Direct Sales', 'description' => 'Direct sales to customers'],
                    ['code' => 'CHANNEL', 'name' => 'Channel Sales', 'description' => 'Partner and channel sales'],
                    ['code' => 'CUSTOMER', 'name' => 'Customer Success', 'description' => 'Customer retention and success'],
                    ['code' => 'SUPPORT', 'name' => 'Sales Support', 'description' => 'Sales operations and support'],
                ]
            ],
            [
                'code' => 'LEGAL',
                'name' => 'Legal & Compliance',
                'description' => 'Provides legal counsel, ensures regulatory compliance, and manages risk.',
                'sections' => [
                    ['code' => 'CONTRACTS', 'name' => 'Contracts', 'description' => 'Contract management and review'],
                    ['code' => 'COMPLIANCE', 'name' => 'Compliance', 'description' => 'Regulatory compliance monitoring'],
                    ['code' => 'RISK', 'name' => 'Risk Management', 'description' => 'Risk assessment and mitigation'],
                    ['code' => 'IP', 'name' => 'Intellectual Property', 'description' => 'IP management and protection'],
                ]
            ],
            [
                'code' => 'EXEC',
                'name' => 'Executive',
                'description' => 'Executive leadership, strategic planning, and corporate governance.',
                'sections' => [
                    ['code' => 'CEO', 'name' => 'Chief Executive Office', 'description' => 'CEO office and executive support'],
                    ['code' => 'STRATEGY', 'name' => 'Strategic Planning', 'description' => 'Corporate strategy and planning'],
                    ['code' => 'BOARD', 'name' => 'Board Relations', 'description' => 'Board of directors support'],
                    ['code' => 'CORP', 'name' => 'Corporate Affairs', 'description' => 'Corporate communications and affairs'],
                ]
            ],
        ];

        foreach ($departments as $deptData) {
            $sections = $deptData['sections'];
            unset($deptData['sections']);

            // Create department
            $department = Department::create($deptData);

            // Create sections for this department
            foreach ($sections as $sectionData) {
                $sectionData['department_id'] = $department->id;
                Section::create($sectionData);
            }
        }

        $this->command->info('Created ' . count($departments) . ' departments with their sections.');
    }
}