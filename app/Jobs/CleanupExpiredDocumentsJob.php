<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentDownload;
use App\Services\NotificationService;
use App\Enums\DocumentStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes timeout
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info('Starting cleanup of expired documents');

        try {
            $this->notifyExpiringDocuments($notificationService);
            $this->archiveExpiredDocuments();
            $this->cleanupOldDownloads();
            $this->cleanupOldNotifications($notificationService);
            $this->cleanupOrphanedFiles();
            
            Log::info('Cleanup of expired documents completed successfully');
            
        } catch (\Exception $e) {
            Log::error('Failed to cleanup expired documents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Notify about documents expiring soon.
     */
    protected function notifyExpiringDocuments(NotificationService $notificationService): void
    {
        $warningDays = [30, 14, 7, 3, 1]; // Days before expiry to send warnings
        
        foreach ($warningDays as $days) {
            $expiringDocuments = Document::where('status', DocumentStatus::PUBLISHED)
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '=', now()->addDays($days)->toDateString())
                ->with(['creator', 'department'])
                ->get();

            foreach ($expiringDocuments as $document) {
                try {
                    $notificationService->notifyDocumentExpiring($document, $days);
                    
                    Log::info("Expiry notification sent for document", [
                        'document_id' => $document->id,
                        'days_until_expiry' => $days,
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Failed to send expiry notification", [
                        'document_id' => $document->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Archive documents that have passed their expiry date.
     */
    protected function archiveExpiredDocuments(): void
    {
        $expiredDocuments = Document::where('status', DocumentStatus::PUBLISHED)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->get();

        $archivedCount = 0;

        foreach ($expiredDocuments as $document) {
            try {
                // Move document to archived status
                $document->update([
                    'status' => DocumentStatus::ARCHIVED,
                ]);

                // Create approval record for the archive action
                $document->approvals()->create([
                    'previous_status' => DocumentStatus::PUBLISHED,
                    'new_status' => DocumentStatus::ARCHIVED,
                    'action' => 'archived_expired',
                    'user_id' => 1, // System user ID
                    'user_role' => 'system',
                    'comments' => 'Document automatically archived due to expiry',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'System/Cleanup Job',
                ]);

                // Move file to archived folder
                $this->moveFileToArchived($document);

                $archivedCount++;

                Log::info("Document automatically archived", [
                    'document_id' => $document->id,
                    'document_number' => $document->document_number,
                    'expiry_date' => $document->expiry_date,
                ]);

            } catch (\Exception $e) {
                Log::error("Failed to archive expired document", [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($archivedCount > 0) {
            Log::info("Archived {$archivedCount} expired documents");
        }
    }

    /**
     * Clean up old document download records.
     */
    protected function cleanupOldDownloads(): void
    {
        $retentionDays = config('app.download_retention_days', 365);
        
        $deletedCount = DocumentDownload::where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} old download records");
        }
    }

    /**
     * Clean up old notifications.
     */
    protected function cleanupOldNotifications(NotificationService $notificationService): void
    {
        $retentionDays = config('app.notification_retention_days', 90);
        
        $deletedCount = $notificationService->cleanupOldNotifications($retentionDays);

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} old notifications");
        }
    }

    /**
     * Clean up orphaned files.
     */
    protected function cleanupOrphanedFiles(): void
    {
        try {
            $this->cleanupOrphanedDocumentFiles();
            $this->cleanupOrphanedQrCodes();
            
        } catch (\Exception $e) {
            Log::warning("Failed to cleanup orphaned files", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clean up orphaned document files.
     */
    protected function cleanupOrphanedDocumentFiles(): void
    {
        $directories = ['documents/drafts', 'documents/submitted', 'documents/published', 'documents/archived'];
        $deletedCount = 0;

        foreach ($directories as $directory) {
            if (!Storage::exists($directory)) {
                continue;
            }

            $files = Storage::allFiles($directory);
            
            foreach ($files as $file) {
                // Check if file is referenced by any document
                $isReferenced = Document::where('file_path', $file)->exists();
                
                if (!$isReferenced) {
                    // Check if file is older than 30 days before deleting
                    $fileAge = now()->timestamp - Storage::lastModified($file);
                    
                    if ($fileAge > (30 * 24 * 60 * 60)) { // 30 days in seconds
                        Storage::delete($file);
                        $deletedCount++;
                        
                        Log::info("Deleted orphaned document file", ['file' => $file]);
                    }
                }
            }
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} orphaned document files");
        }
    }

    /**
     * Clean up orphaned QR code files.
     */
    protected function cleanupOrphanedQrCodes(): void
    {
        if (!Storage::exists('qrcodes')) {
            return;
        }

        $qrFiles = Storage::allFiles('qrcodes');
        $deletedCount = 0;

        foreach ($qrFiles as $file) {
            // Check if QR code is referenced by any document
            $isReferenced = Document::where('qr_code_path', $file)->exists();
            
            if (!$isReferenced) {
                // Check if file is older than 7 days before deleting
                $fileAge = now()->timestamp - Storage::lastModified($file);
                
                if ($fileAge > (7 * 24 * 60 * 60)) { // 7 days in seconds
                    Storage::delete($file);
                    $deletedCount++;
                    
                    Log::info("Deleted orphaned QR code file", ['file' => $file]);
                }
            }
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} orphaned QR code files");
        }
    }

    /**
     * Move document file to archived folder.
     */
    protected function moveFileToArchived(Document $document): void
    {
        if (!$document->file_path || !Storage::exists($document->file_path)) {
            return;
        }

        $currentPath = $document->file_path;
        $fileName = basename($currentPath);
        $newPath = "documents/archived/" . now()->format('Y/m') . "/{$fileName}";

        // Ensure directory exists
        Storage::makeDirectory(dirname($newPath));

        // Move file
        if (Storage::move($currentPath, $newPath)) {
            $document->update(['file_path' => $newPath]);
            
            Log::info("Moved document file to archived folder", [
                'document_id' => $document->id,
                'old_path' => $currentPath,
                'new_path' => $newPath,
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CleanupExpiredDocumentsJob failed", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['maintenance', 'cleanup', 'documents'];
    }
}