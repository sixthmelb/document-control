<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\DocumentApproval;
use App\Models\User;
use App\Models\Department;
use App\Models\Section;
use App\Enums\DocumentStatus;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get users for document creation
        $superAdmin = User::where('role', UserRole::SUPERADMIN)->first();
        $itAdmin = User::where('role', UserRole::ADMIN)->where('email', 'it.admin@akm.com')->first();
        $hrAdmin = User::where('role', UserRole::ADMIN)->where('email', 'hr.admin@akm.com')->first();
        $regularUser = User::where('role', UserRole::USER)->first();

        // Get departments and sections
        $itDepartment = Department::where('code', 'IT')->first();
        $hrDepartment = Department::where('code', 'HR')->first();
        $finDepartment = Department::where('code', 'FIN')->first();
        
        $itDevSection = Section::where('code', 'DEV')->where('department_id', $itDepartment->id)->first();
        $itSupportSection = Section::where('code', 'SUPPORT')->where('department_id', $itDepartment->id)->first();
        $hrRecruitSection = Section::where('code', 'RECRUIT')->where('department_id', $hrDepartment->id)->first();
        $finAccountSection = Section::where('code', 'ACCOUNT')->where('department_id', $finDepartment->id)->first();

        // Create sample documents
        $documents = [
            // Published documents
            [
                'title' => 'IT Security Policy',
                'description' => 'Company-wide information security policy and guidelines for all employees.',
                'document_type' => 'policy',
                'status' => DocumentStatus::PUBLISHED,
                'creator_id' => $itAdmin->id,
                'department_id' => $itDepartment->id,
                'section_id' => $itDevSection->id,
                'version' => '2.1',
                'effective_date' => now()->subMonths(6),
                'expiry_date' => now()->addYear(),
                'tags' => ['security', 'policy', 'IT', 'mandatory'],
                'is_confidential' => false,
                'published_at' => now()->subMonths(3),
                'approved_by' => $superAdmin->id,
                'approved_at' => now()->subMonths(3)->subDays(1),
            ],
            [
                'title' => 'Employee Handbook 2025',
                'description' => 'Comprehensive guide for new and existing employees covering company policies, procedures, and benefits.',
                'document_type' => 'manual',
                'status' => DocumentStatus::PUBLISHED,
                'creator_id' => $hrAdmin->id,
                'department_id' => $hrDepartment->id,
                'section_id' => $hrRecruitSection->id,
                'version' => '1.0',
                'effective_date' => now()->startOfYear(),
                'expiry_date' => now()->endOfYear(),
                'tags' => ['handbook', 'HR', 'employees', 'policies'],
                'is_confidential' => false,
                'published_at' => now()->subMonths(2),
                'approved_by' => $superAdmin->id,
                'approved_at' => now()->subMonths(2)->subDays(1),
            ],
            [
                'title' => 'Software Development Standards',
                'description' => 'Coding standards, best practices, and development guidelines for the IT development team.',
                'document_type' => 'guideline',
                'status' => DocumentStatus::PUBLISHED,
                'creator_id' => $regularUser->id,
                'department_id' => $itDepartment->id,
                'section_id' => $itDevSection->id,
                'version' => '1.2',
                'effective_date' => now()->subMonth(),
                'expiry_date' => now()->addMonths(18),
                'tags' => ['development', 'standards', 'coding', 'guidelines'],
                'is_confidential' => false,
                'published_at' => now()->subMonth(),
                'approved_by' => $superAdmin->id,
                'approved_at' => now()->subMonth()->subDays(1),
            ],
            [
                'title' => 'Financial Reporting Procedures',
                'description' => 'Step-by-step procedures for monthly and quarterly financial reporting.',
                'document_type' => 'procedure',
                'status' => DocumentStatus::PUBLISHED,
                'creator_id' => $regularUser->id,
                'department_id' => $finDepartment->id,
                'section_id' => $finAccountSection->id,
                'version' => '1.0',
                'effective_date' => now()->subWeeks(2),
                'expiry_date' => now()->addYear(),
                'tags' => ['finance', 'reporting', 'procedures', 'monthly'],
                'is_confidential' => false,
                'published_at' => now()->subWeeks(2),
                'approved_by' => $superAdmin->id,
                'approved_at' => now()->subWeeks(2)->subDays(1),
            ],

            // Approved document (ready to publish)
            [
                'title' => 'Remote Work Policy',
                'description' => 'Updated policy for remote work arrangements and guidelines.',
                'document_type' => 'policy',
                'status' => DocumentStatus::APPROVED,
                'creator_id' => $hrAdmin->id,
                'department_id' => $hrDepartment->id,
                'section_id' => $hrRecruitSection->id,
                'version' => '1.0',
                'effective_date' => now()->addWeek(),
                'expiry_date' => now()->addYear(),
                'tags' => ['remote work', 'policy', 'HR', 'flexible'],
                'is_confidential' => false,
                'approved_by' => $superAdmin->id,
                'approved_at' => now()->subDays(2),
            ],

            // Verified document (waiting for approval)
            [
                'title' => 'Data Backup Procedures',
                'description' => 'Comprehensive data backup and recovery procedures for critical systems.',
                'document_type' => 'procedure',
                'status' => DocumentStatus::VERIFIED,
                'creator_id' => $regularUser->id,
                'department_id' => $itDepartment->id,
                'section_id' => $itSupportSection->id,
                'version' => '1.0',
                'tags' => ['backup', 'data', 'recovery', 'procedures'],
                'is_confidential' => false,
                'verified_at' => now()->subDays(1),
            ],

            // Under review document
            [
                'title' => 'Expense Reimbursement Guidelines',
                'description' => 'Updated guidelines for employee expense reimbursement process.',
                'document_type' => 'guideline',
                'status' => DocumentStatus::UNDER_REVIEW,
                'creator_id' => $regularUser->id,
                'department_id' => $finDepartment->id,
                'section_id' => $finAccountSection->id,
                'version' => '1.0',
                'tags' => ['expenses', 'reimbursement', 'finance', 'guidelines'],
                'is_confidential' => false,
                'submitted_at' => now()->subDays(3),
                'current_reviewer_id' => $superAdmin->id,
            ],

            // Submitted document
            [
                'title' => 'IT Support Ticket Process',
                'description' => 'Standard operating procedure for handling IT support tickets.',
                'document_type' => 'sop',
                'status' => DocumentStatus::SUBMITTED,
                'creator_id' => $regularUser->id,
                'department_id' => $itDepartment->id,
                'section_id' => $itSupportSection->id,
                'version' => '1.0',
                'tags' => ['IT', 'support', 'tickets', 'SOP'],
                'is_confidential' => false,
                'submitted_at' => now()->subDay(),
            ],

            // Draft documents
            [
                'title' => 'Performance Review Process',
                'description' => 'Annual performance review process and evaluation criteria.',
                'document_type' => 'procedure',
                'status' => DocumentStatus::DRAFT,
                'creator_id' => $hrAdmin->id,
                'department_id' => $hrDepartment->id,
                'section_id' => $hrRecruitSection->id,
                'version' => '1.0',
                'tags' => ['performance', 'review', 'evaluation', 'HR'],
                'is_confidential' => false,
            ],
            [
                'title' => 'Security Incident Response Plan',
                'description' => 'Comprehensive plan for responding to cybersecurity incidents.',
                'document_type' => 'procedure',
                'status' => DocumentStatus::DRAFT,
                'creator_id' => $itAdmin->id,
                'department_id' => $itDepartment->id,
                'section_id' => $itDevSection->id,
                'version' => '1.0',
                'tags' => ['security', 'incident', 'response', 'cybersecurity'],
                'is_confidential' => true,
            ],

            // Document needing revision
            [
                'title' => 'Travel Policy Update',
                'description' => 'Updated travel policy including new expense limits and approval processes.',
                'document_type' => 'policy',
                'status' => DocumentStatus::NEEDS_REVISION,
                'creator_id' => $regularUser->id,
                'department_id' => $finDepartment->id,
                'section_id' => $finAccountSection->id,
                'version' => '1.0',
                'tags' => ['travel', 'policy', 'expenses', 'approval'],
                'is_confidential' => false,
                'submitted_at' => now()->subDays(5),
            ],
        ];

        foreach ($documents as $docData) {
            // Generate document number
            $department = Department::find($docData['department_id']);
            $section = Section::find($docData['section_id']);
            $docData['document_number'] = $department->generateDocumentNumber($section);

            // Create dummy file data
            $docData = array_merge($docData, $this->createDummyFile($docData['title']));

            // Set metadata
            $docData['metadata'] = [
                'created_by_seeder' => true,
                'sample_document' => true,
            ];

            // Create the document
            $document = Document::create($docData);

            // Create initial revision
            DocumentRevision::createFromDocument(
                $document, 
                User::find($docData['creator_id']), 
                'Initial version created by seeder'
            );

            // Create approval trail based on status
            $this->createApprovalTrail($document);

            // Simulate some view and download counts for published documents
            if ($document->status === DocumentStatus::PUBLISHED) {
                $document->update([
                    'view_count' => rand(50, 500),
                    'download_count' => rand(10, 100),
                ]);

                // Generate QR code for published documents (would normally be done by service)
                $document->update([
                    'validation_token' => hash('sha256', $document->id . $document->document_number . now()->timestamp),
                ]);
            }
        }

        $this->command->info('Created ' . count($documents) . ' sample documents with various statuses.');
    }

    /**
     * Create dummy file data for document.
     */
    private function createDummyFile(string $title): array
    {
        $fileName = Str::slug($title) . '.pdf';
        $filePath = "documents/samples/{$fileName}";
        
        // Create dummy file content
        $content = "This is a sample document: {$title}\n\nGenerated by DocumentSeeder for demonstration purposes.";
        
        // Ensure directory exists
        Storage::makeDirectory('documents/samples');
        
        // Store dummy file
        Storage::put($filePath, $content);

        return [
            'original_filename' => $fileName,
            'file_path' => $filePath,
            'file_type' => 'pdf',
            'file_size' => strlen($content),
            'file_hash' => hash('sha256', $content),
        ];
    }

    /**
     * Create approval trail based on document status.
     */
    private function createApprovalTrail(Document $document): void
    {
        $creator = $document->creator;
        $superAdmin = User::where('role', UserRole::SUPERADMIN)->first();
        $itAdmin = User::where('role', UserRole::ADMIN)->where('email', 'it.admin@akm.com')->first();

        $approvals = [];

        // All documents start as draft
        $approvals[] = [
            'document_id' => $document->id,
            'previous_status' => DocumentStatus::DRAFT,
            'new_status' => DocumentStatus::DRAFT,
            'action' => 'created',
            'user_id' => $creator->id,
            'user_role' => $creator->role->value,
            'comments' => 'Document created',
            'created_at' => $document->created_at,
        ];

        // Add approvals based on current status
        if (in_array($document->status, [
            DocumentStatus::SUBMITTED, 
            DocumentStatus::UNDER_REVIEW, 
            DocumentStatus::NEEDS_REVISION,
            DocumentStatus::VERIFIED, 
            DocumentStatus::APPROVED, 
            DocumentStatus::PUBLISHED
        ])) {
            $approvals[] = [
                'document_id' => $document->id,
                'previous_status' => DocumentStatus::DRAFT,
                'new_status' => DocumentStatus::SUBMITTED,
                'action' => 'submitted',
                'user_id' => $creator->id,
                'user_role' => $creator->role->value,
                'comments' => 'Document submitted for review',
                'created_at' => $document->submitted_at ?: $document->created_at->addHours(1),
            ];
        }

        if (in_array($document->status, [
            DocumentStatus::UNDER_REVIEW, 
            DocumentStatus::NEEDS_REVISION,
            DocumentStatus::VERIFIED, 
            DocumentStatus::APPROVED, 
            DocumentStatus::PUBLISHED
        ])) {
            $approvals[] = [
                'document_id' => $document->id,
                'previous_status' => DocumentStatus::SUBMITTED,
                'new_status' => DocumentStatus::UNDER_REVIEW,
                'action' => 'reviewed',
                'user_id' => $itAdmin->id,
                'user_role' => $itAdmin->role->value,
                'comments' => 'Started document review',
                'created_at' => $document->created_at->addHours(2),
            ];
        }

        if ($document->status === DocumentStatus::NEEDS_REVISION) {
            $approvals[] = [
                'document_id' => $document->id,
                'previous_status' => DocumentStatus::UNDER_REVIEW,
                'new_status' => DocumentStatus::NEEDS_REVISION,
                'action' => 'requested_revision',
                'user_id' => $itAdmin->id,
                'user_role' => $itAdmin->role->value,
                'comments' => 'Please review and update the formatting section. Also add more details about implementation timeline.',
                'created_at' => $document->created_at->addHours(3),
            ];
        }

        if (in_array($document->status, [
            DocumentStatus::VERIFIED, 
            DocumentStatus::APPROVED, 
            DocumentStatus::PUBLISHED
        ])) {
            $approvals[] = [
                'document_id' => $document->id,
                'previous_status' => DocumentStatus::UNDER_REVIEW,
                'new_status' => DocumentStatus::VERIFIED,
                'action' => 'verified',
                'user_id' => $itAdmin->id,
                'user_role' => $itAdmin->role->value,
                'comments' => 'Document verified and ready for approval',
                'created_at' => $document->verified_at ?: $document->created_at->addHours(4),
            ];
        }

        if (in_array($document->status, [
            DocumentStatus::APPROVED, 
            DocumentStatus::PUBLISHED
        ])) {
            $approvals[] = [
                'document_id' => $document->id,
                'previous_status' => DocumentStatus::VERIFIED,
                'new_status' => DocumentStatus::APPROVED,
                'action' => 'approved',
                'user_id' => $superAdmin->id,
                'user_role' => $superAdmin->role->value,
                'comments' => 'Document approved for publication',
                'created_at' => $document->approved_at ?: $document->created_at->addHours(5),
            ];
        }

        if ($document->status === DocumentStatus::PUBLISHED) {
            $approvals[] = [
                'document_id' => $document->id,
                'previous_status' => DocumentStatus::APPROVED,
                'new_status' => DocumentStatus::PUBLISHED,
                'action' => 'published',
                'user_id' => $superAdmin->id,
                'user_role' => $superAdmin->role->value,
                'comments' => 'Document published and available to public',
                'created_at' => $document->published_at ?: $document->created_at->addHours(6),
            ];
        }

        // Insert all approvals
        foreach ($approvals as $approval) {
            DocumentApproval::create($approval);
        }
    }
}