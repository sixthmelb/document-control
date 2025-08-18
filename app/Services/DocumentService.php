<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentRevision;
use App\Models\User;
use App\Models\Department;
use App\Models\Section;
use App\Enums\DocumentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentService
{
    protected QrCodeService $qrCodeService;
    protected NotificationService $notificationService;

    public function __construct(
        QrCodeService $qrCodeService,
        NotificationService $notificationService
    ) {
        $this->qrCodeService = $qrCodeService;
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new document.
     */
    public function createDocument(array $data, UploadedFile $file, User $creator): Document
    {
        return DB::transaction(function () use ($data, $file, $creator) {
            // Get department and section
            $department = Department::findOrFail($data['department_id']);
            $section = Section::findOrFail($data['section_id']);
            
            // Generate document number
            $documentNumber = $department->generateDocumentNumber($section);
            
            // Store file
            $fileData = $this->storeFile($file, 'drafts');
            
            // Create document
            $document = Document::create([
                'document_number' => $documentNumber,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'document_type' => $data['document_type'] ?? 'general',
                'status' => DocumentStatus::DRAFT,
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $fileData['path'],
                'file_type' => $fileData['type'],
                'file_size' => $fileData['size'],
                'file_hash' => $fileData['hash'],
                'version' => '1.0',
                'effective_date' => $data['effective_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'tags' => $data['tags'] ?? [],
                'metadata' => $data['metadata'] ?? [],
                'creator_id' => $creator->id,
                'department_id' => $department->id,
                'section_id' => $section->id,
                'is_confidential' => $data['is_confidential'] ?? false,
            ]);

            // Create initial revision
            DocumentRevision::createFromDocument($document, $creator, 'Initial version');

            return $document;
        });
    }

    /**
     * Update document.
     */
    public function updateDocument(Document $document, array $data, ?UploadedFile $file = null, User $updater): Document
    {
        return DB::transaction(function () use ($document, $data, $file, $updater) {
            $updateData = [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'document_type' => $data['document_type'] ?? $document->document_type,
                'effective_date' => $data['effective_date'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'tags' => $data['tags'] ?? [],
                'metadata' => $data['metadata'] ?? [],
                'is_confidential' => $data['is_confidential'] ?? false,
            ];

            // If new file is uploaded
            if ($file) {
                // Store new file
                $fileData = $this->storeFile($file, 'drafts');
                
                // Delete old file if exists
                if ($document->file_path && Storage::exists($document->file_path)) {
                    Storage::delete($document->file_path);
                }
                
                $updateData = array_merge($updateData, [
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $fileData['path'],
                    'file_type' => $fileData['type'],
                    'file_size' => $fileData['size'],
                    'file_hash' => $fileData['hash'],
                ]);

                // Create new revision if file changed
                $isNewRevision = $data['is_major_revision'] ?? false;
                DocumentRevision::createFromDocument(
                    $document, 
                    $updater, 
                    $data['revision_notes'] ?? 'File updated',
                    $isNewRevision
                );
            }

            $document->update($updateData);

            return $document;
        });
    }

    /**
     * Submit document for review.
     */
    public function submitForReview(Document $document, User $submitter): bool
    {
        if (!in_array($document->status, [DocumentStatus::DRAFT, DocumentStatus::NEEDS_REVISION])) {
            throw new \InvalidArgumentException('Document cannot be submitted in current status');
        }

        // Check if document has file
        if (!$document->hasFile()) {
            throw new \InvalidArgumentException('Document must have a file attached before submission');
        }

        $success = $document->updateStatus(DocumentStatus::SUBMITTED, $submitter);

        if ($success) {
            // Move file to submitted folder if exists
            if ($document->file_path && Storage::exists($document->file_path)) {
                $this->moveFile($document, 'drafts', 'submitted');
            }
            
            // Notify administrators
            $this->notificationService->notifyDocumentSubmitted($document);
        }

        return $success;
    }

    /**
     * Start review process.
     */
    public function startReview(Document $document, User $reviewer): bool
    {
        if ($document->status !== DocumentStatus::SUBMITTED) {
            throw new \InvalidArgumentException('Document must be submitted before review');
        }

        if (!$reviewer->canReview()) {
            throw new \InvalidArgumentException('User cannot review documents');
        }

        return $document->updateStatus(DocumentStatus::UNDER_REVIEW, $reviewer);
    }

    /**
     * Request revision.
     */
    public function requestRevision(Document $document, User $reviewer, string $revisionNotes): bool
    {
        if ($document->status !== DocumentStatus::UNDER_REVIEW) {
            throw new \InvalidArgumentException('Document must be under review');
        }

        $success = $document->updateStatus(
            DocumentStatus::NEEDS_REVISION, 
            $reviewer, 
            $revisionNotes
        );

        if ($success) {
            // Move file back to drafts
            $this->moveFile($document, 'submitted', 'drafts');
            
            // Notify creator
            $this->notificationService->notifyRevisionRequested($document, $revisionNotes);
        }

        return $success;
    }

    /**
     * Verify document.
     */
    public function verifyDocument(Document $document, User $verifier): bool
    {
        if ($document->status !== DocumentStatus::UNDER_REVIEW) {
            throw new \InvalidArgumentException('Document must be under review');
        }

        if (!$verifier->canReview()) {
            throw new \InvalidArgumentException('User cannot verify documents');
        }

        $success = $document->updateStatus(DocumentStatus::VERIFIED, $verifier);

        if ($success) {
            // Notify superadmins for approval
            $this->notificationService->notifyDocumentVerified($document);
        }

        return $success;
    }

    /**
     * Approve document.
     */
    public function approveDocument(Document $document, User $approver): bool
    {
        if ($document->status !== DocumentStatus::VERIFIED) {
            throw new \InvalidArgumentException('Document must be verified before approval');
        }

        if (!$approver->canApprove()) {
            throw new \InvalidArgumentException('User cannot approve documents');
        }

        return $document->updateStatus(DocumentStatus::APPROVED, $approver);
    }

    /**
     * Publish document.
     */
    public function publishDocument(Document $document, User $publisher): bool
    {
        if ($document->status !== DocumentStatus::APPROVED) {
            throw new \InvalidArgumentException('Document must be approved before publishing');
        }

        return DB::transaction(function () use ($document, $publisher) {
            // Update status
            $success = $document->updateStatus(DocumentStatus::PUBLISHED, $publisher);

            if ($success) {
                // Move file to published folder
                $this->moveFile($document, 'submitted', 'published');
                
                // Generate QR code
                $this->qrCodeService->generateQrCode($document);
                
                // Notify stakeholders
                $this->notificationService->notifyDocumentPublished($document);
            }

            return $success;
        });
    }

    /**
     * Reject document.
     */
    public function rejectDocument(Document $document, User $rejector, string $reason): bool
    {
        if (!in_array($document->status, [DocumentStatus::UNDER_REVIEW, DocumentStatus::VERIFIED])) {
            throw new \InvalidArgumentException('Document cannot be rejected in current status');
        }

        $success = $document->updateStatus(DocumentStatus::REJECTED, $rejector, $reason);

        if ($success) {
            // Move file to rejected folder
            $this->moveFile($document, null, 'rejected');
            
            // Notify creator
            $this->notificationService->notifyDocumentRejected($document, $reason);
        }

        return $success;
    }

    /**
     * Archive document.
     */
    public function archiveDocument(Document $document, User $archiver): bool
    {
        if ($document->status !== DocumentStatus::PUBLISHED) {
            throw new \InvalidArgumentException('Only published documents can be archived');
        }

        $success = $document->updateStatus(DocumentStatus::ARCHIVED, $archiver);

        if ($success) {
            // Move file to archived folder
            $this->moveFile($document, 'published', 'archived');
        }

        return $success;
    }

    /**
     * Delete document.
     */
    public function deleteDocument(Document $document, User $deleter): bool
    {
        if ($document->isPublished()) {
            throw new \InvalidArgumentException('Published documents cannot be deleted');
        }

        return DB::transaction(function () use ($document) {
            // Delete file
            if ($document->file_path && Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }

            // Delete QR code if exists
            if ($document->qr_code_path && Storage::exists($document->qr_code_path)) {
                Storage::delete($document->qr_code_path);
            }

            // Delete document (soft delete)
            return $document->delete();
        });
    }

    /**
     * Store uploaded file.
     */
    protected function storeFile(UploadedFile $file, string $folder): array
    {
        $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = "documents/{$folder}/" . now()->format('Y/m') . "/{$fileName}";
        
        // Store file
        $storedPath = $file->storeAs(dirname($path), basename($path), 'local');
        
        return [
            'path' => $storedPath,
            'type' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'hash' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    /**
     * Move file between folders.
     */
    protected function moveFile(Document $document, ?string $fromFolder, string $toFolder): bool
    {
        if (!$document->file_path || !Storage::exists($document->file_path)) {
            return false;
        }

        $currentPath = $document->file_path;
        $fileName = basename($currentPath);
        $newPath = "documents/{$toFolder}/" . now()->format('Y/m') . "/{$fileName}";

        // Ensure directory exists
        Storage::makeDirectory(dirname($newPath));

        // Move file
        if (Storage::move($currentPath, $newPath)) {
            $document->update(['file_path' => $newPath]);
            return true;
        }

        return false;
    }

    /**
     * Get document statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total' => Document::count(),
            'by_status' => Document::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_department' => Document::with('department')
                ->select('department_id', DB::raw('count(*) as count'))
                ->groupBy('department_id')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->department->name => $item->count];
                })
                ->toArray(),
            'recent_activity' => Document::with(['creator', 'approvals.user'])
                ->where('updated_at', '>=', now()->subDays(7))
                ->orderBy('updated_at', 'desc')
                ->take(10)
                ->get(),
        ];
    }
}