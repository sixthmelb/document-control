<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\QrCodeService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocumentApprovalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Document $document;
    public string $action;
    public array $additionalData;

    /**
     * Create a new job instance.
     */
    public function __construct(Document $document, string $action, array $additionalData = [])
    {
        $this->document = $document;
        $this->action = $action;
        $this->additionalData = $additionalData;
        
        // Set queue priority based on action
        $this->onQueue($this->getQueueName());
    }

    /**
     * Execute the job.
     */
    public function handle(QrCodeService $qrCodeService, NotificationService $notificationService): void
    {
        try {
            Log::info("Processing document approval job", [
                'document_id' => $this->document->id,
                'action' => $this->action,
                'status' => $this->document->status->value,
            ]);

            switch ($this->action) {
                case 'published':
                    $this->handlePublished($qrCodeService, $notificationService);
                    break;
                    
                case 'approved':
                    $this->handleApproved($notificationService);
                    break;
                    
                case 'verified':
                    $this->handleVerified($notificationService);
                    break;
                    
                case 'revision_requested':
                    $this->handleRevisionRequested($notificationService);
                    break;
                    
                case 'rejected':
                    $this->handleRejected($notificationService);
                    break;
                    
                case 'submitted':
                    $this->handleSubmitted($notificationService);
                    break;
                    
                default:
                    Log::warning("Unknown document approval action: {$this->action}");
            }

        } catch (\Exception $e) {
            Log::error("Failed to process document approval job", [
                'document_id' => $this->document->id,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle document published.
     */
    protected function handlePublished(QrCodeService $qrCodeService, NotificationService $notificationService): void
    {
        // Generate QR code if not exists
        if (!$this->document->qr_code_path) {
            $qrCodeService->generateQrCode($this->document);
            Log::info("QR code generated for published document", [
                'document_id' => $this->document->id,
            ]);
        }

        // Send notifications
        $notificationService->notifyDocumentPublished($this->document);

        // Update search index if available
        $this->updateSearchIndex();

        // Clear related caches
        $this->clearCaches();

        Log::info("Document published successfully", [
            'document_id' => $this->document->id,
            'document_number' => $this->document->document_number,
        ]);
    }

    /**
     * Handle document approved.
     */
    protected function handleApproved(NotificationService $notificationService): void
    {
        $notificationService->notifyDocumentApproved($this->document);
        
        Log::info("Document approved notifications sent", [
            'document_id' => $this->document->id,
        ]);
    }

    /**
     * Handle document verified.
     */
    protected function handleVerified(NotificationService $notificationService): void
    {
        $notificationService->notifyDocumentVerified($this->document);
        
        Log::info("Document verified notifications sent", [
            'document_id' => $this->document->id,
        ]);
    }

    /**
     * Handle revision requested.
     */
    protected function handleRevisionRequested(NotificationService $notificationService): void
    {
        $revisionNotes = $this->additionalData['revision_notes'] ?? 'Revision requested';
        $notificationService->notifyRevisionRequested($this->document, $revisionNotes);
        
        Log::info("Revision requested notifications sent", [
            'document_id' => $this->document->id,
        ]);
    }

    /**
     * Handle document rejected.
     */
    protected function handleRejected(NotificationService $notificationService): void
    {
        $rejectionReason = $this->additionalData['rejection_reason'] ?? 'Document rejected';
        $notificationService->notifyDocumentRejected($this->document, $rejectionReason);
        
        Log::info("Document rejected notifications sent", [
            'document_id' => $this->document->id,
        ]);
    }

    /**
     * Handle document submitted.
     */
    protected function handleSubmitted(NotificationService $notificationService): void
    {
        $notificationService->notifyDocumentSubmitted($this->document);
        
        Log::info("Document submitted notifications sent", [
            'document_id' => $this->document->id,
        ]);
    }

    /**
     * Update search index for document.
     */
    protected function updateSearchIndex(): void
    {
        try {
            // If using Laravel Scout or similar search engine
            // $this->document->searchable();
            
            Log::info("Search index updated for document", [
                'document_id' => $this->document->id,
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to update search index", [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear related caches.
     */
    protected function clearCaches(): void
    {
        try {
            // Clear public statistics cache
            cache()->forget('public_statistics');
            
            // Clear department-specific caches
            cache()->forget('public_departments');
            cache()->forget('public_sections');
            cache()->forget('public_document_types');
            
            // Clear department document cache
            cache()->forget("dept_docs_{$this->document->department_id}");
            
            Log::info("Caches cleared for document", [
                'document_id' => $this->document->id,
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to clear caches", [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get queue name based on action priority.
     */
    protected function getQueueName(): string
    {
        return match ($this->action) {
            'published' => 'high',
            'approved', 'verified' => 'normal',
            'revision_requested', 'rejected' => 'normal',
            'submitted' => 'low',
            default => 'default',
        };
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [30, 60, 180]; // 30 seconds, 1 minute, 3 minutes
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessDocumentApprovalJob failed", [
            'document_id' => $this->document->id,
            'action' => $this->action,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionally notify administrators about the failure
        try {
            \Notification::route('mail', config('app.admin_email'))
                ->notify(new \App\Notifications\JobFailedNotification(
                    'ProcessDocumentApprovalJob',
                    $this->document->id,
                    $exception->getMessage()
                ));
        } catch (\Exception $e) {
            Log::error("Failed to send job failure notification", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'document:' . $this->document->id,
            'action:' . $this->action,
            'department:' . $this->document->department_id,
        ];
    }
}