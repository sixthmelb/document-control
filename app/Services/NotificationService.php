<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DocumentStatusChanged;
use App\Mail\DocumentApprovalNotification;

class NotificationService
{
    /**
     * Notify when document is submitted for review.
     */
    public function notifyDocumentSubmitted(Document $document): void
    {
        // Get all admins and superadmins
        $reviewers = User::active()
            ->administrators()
            ->get();

        foreach ($reviewers as $reviewer) {
            $this->sendDocumentNotification($reviewer, $document, 'submitted');
        }
    }

    /**
     * Notify when revision is requested.
     */
    public function notifyRevisionRequested(Document $document, string $revisionNotes): void
    {
        $creator = $document->creator;
        
        if ($creator && $creator->is_active) {
            $this->sendDocumentNotification($creator, $document, 'revision_requested', [
                'revision_notes' => $revisionNotes
            ]);
        }
    }

    /**
     * Notify when document is verified.
     */
    public function notifyDocumentVerified(Document $document): void
    {
        // Get all superadmins for final approval
        $approvers = User::active()
            ->byRole(UserRole::SUPERADMIN)
            ->get();

        foreach ($approvers as $approver) {
            $this->sendDocumentNotification($approver, $document, 'verified');
        }
    }

    /**
     * Notify when document is approved.
     */
    public function notifyDocumentApproved(Document $document): void
    {
        $creator = $document->creator;
        
        if ($creator && $creator->is_active) {
            $this->sendDocumentNotification($creator, $document, 'approved');
        }

        // Also notify department admins
        $departmentAdmins = User::active()
            ->administrators()
            ->inDepartment($document->department_id)
            ->get();

        foreach ($departmentAdmins as $admin) {
            $this->sendDocumentNotification($admin, $document, 'approved');
        }
    }

    /**
     * Notify when document is published.
     */
    public function notifyDocumentPublished(Document $document): void
    {
        // Notify creator
        $creator = $document->creator;
        if ($creator && $creator->is_active) {
            $this->sendDocumentNotification($creator, $document, 'published');
        }

        // Notify all users in the same department
        $departmentUsers = User::active()
            ->inDepartment($document->department_id)
            ->get();

        foreach ($departmentUsers as $user) {
            if ($user->id !== $creator->id) {
                $this->sendDocumentNotification($user, $document, 'published');
            }
        }

        // Send email to stakeholders if specified
        if (isset($document->metadata['stakeholder_emails'])) {
            $this->notifyExternalStakeholders($document);
        }
    }

    /**
     * Notify when document is rejected.
     */
    public function notifyDocumentRejected(Document $document, string $reason): void
    {
        $creator = $document->creator;
        
        if ($creator && $creator->is_active) {
            $this->sendDocumentNotification($creator, $document, 'rejected', [
                'rejection_reason' => $reason
            ]);
        }
    }

    /**
     * Notify about document expiry.
     */
    public function notifyDocumentExpiring(Document $document, int $daysUntilExpiry): void
    {
        // Notify creator
        $creator = $document->creator;
        if ($creator && $creator->is_active) {
            $this->sendDocumentNotification($creator, $document, 'expiring', [
                'days_until_expiry' => $daysUntilExpiry
            ]);
        }

        // Notify department admins
        $departmentAdmins = User::active()
            ->administrators()
            ->inDepartment($document->department_id)
            ->get();

        foreach ($departmentAdmins as $admin) {
            $this->sendDocumentNotification($admin, $document, 'expiring', [
                'days_until_expiry' => $daysUntilExpiry
            ]);
        }
    }

    /**
     * Notify about overdue reviews.
     */
    public function notifyOverdueReviews(): void
    {
        // Get documents that have been submitted for more than X days without review
        $overdueDocuments = Document::where('status', 'submitted')
            ->where('submitted_at', '<', now()->subDays(config('app.review_deadline_days', 3)))
            ->with(['creator', 'department'])
            ->get();

        if ($overdueDocuments->count() === 0) {
            return;
        }

        // Group by department
        $groupedDocuments = $overdueDocuments->groupBy('department_id');

        foreach ($groupedDocuments as $departmentId => $documents) {
            // Notify department admins
            $departmentAdmins = User::active()
                ->administrators()
                ->inDepartment($departmentId)
                ->get();

            foreach ($departmentAdmins as $admin) {
                $this->sendOverdueReviewNotification($admin, $documents);
            }
        }

        // Also notify superadmins
        $superAdmins = User::active()
            ->byRole(UserRole::SUPERADMIN)
            ->get();

        foreach ($superAdmins as $superAdmin) {
            $this->sendOverdueReviewNotification($superAdmin, $overdueDocuments);
        }
    }

    /**
     * Send document notification to user.
     */
    protected function sendDocumentNotification(User $user, Document $document, string $type, array $additionalData = []): void
    {
        $notificationData = array_merge([
            'document_id' => $document->id,
            'document_title' => $document->title,
            'document_number' => $document->document_number,
            'type' => $type,
            'message' => $this->getNotificationMessage($type, $document),
        ], $additionalData);

        // Send in-app notification
        $user->notify(new DocumentStatusChanged($notificationData));

        // Send email if user prefers email notifications
        if ($this->shouldSendEmail($user, $type)) {
            Mail::to($user->email)->send(new DocumentApprovalNotification($document, $type, $additionalData));
        }
    }

    /**
     * Send overdue review notification.
     */
    protected function sendOverdueReviewNotification(User $user, $documents): void
    {
        $notificationData = [
            'type' => 'overdue_reviews',
            'count' => is_countable($documents) ? $documents->count() : 1,
            'documents' => is_countable($documents) ? $documents->toArray() : [$documents],
            'message' => 'You have overdue document reviews that require attention.',
        ];

        $user->notify(new DocumentStatusChanged($notificationData));

        // Send email
        if ($this->shouldSendEmail($user, 'overdue_reviews')) {
            Mail::to($user->email)->send(new DocumentApprovalNotification(null, 'overdue_reviews', $notificationData));
        }
    }

    /**
     * Notify external stakeholders via email.
     */
    protected function notifyExternalStakeholders(Document $document): void
    {
        $stakeholderEmails = $document->metadata['stakeholder_emails'] ?? [];
        
        if (empty($stakeholderEmails)) {
            return;
        }

        foreach ($stakeholderEmails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($email)->send(new DocumentApprovalNotification($document, 'published_external'));
                } catch (\Exception $e) {
                    // Log email sending failure
                    \Log::warning("Failed to send notification to external stakeholder: {$email}", [
                        'document_id' => $document->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Get notification message based on type.
     */
    protected function getNotificationMessage(string $type, Document $document): string
    {
        return match ($type) {
            'submitted' => "Document '{$document->title}' has been submitted for review.",
            'revision_requested' => "Revision has been requested for document '{$document->title}'.",
            'verified' => "Document '{$document->title}' has been verified and is awaiting final approval.",
            'approved' => "Document '{$document->title}' has been approved.",
            'published' => "Document '{$document->title}' has been published and is now available.",
            'rejected' => "Document '{$document->title}' has been rejected.",
            'expiring' => "Document '{$document->title}' is approaching its expiry date.",
            default => "Document '{$document->title}' status has been updated.",
        };
    }

    /**
     * Check if user should receive email notification.
     */
    protected function shouldSendEmail(User $user, string $type): bool
    {
        $preferences = $user->preferences ?? [];
        $emailNotifications = $preferences['email_notifications'] ?? [];

        // Default email preferences
        $defaultPreferences = [
            'submitted' => false,
            'revision_requested' => true,
            'verified' => false,
            'approved' => true,
            'published' => true,
            'rejected' => true,
            'expiring' => true,
            'overdue_reviews' => true,
        ];

        return $emailNotifications[$type] ?? $defaultPreferences[$type] ?? false;
    }

    /**
     * Send bulk notifications to multiple users.
     */
    public function sendBulkNotification(array $userIds, string $message, string $type = 'general'): void
    {
        $users = User::active()->whereIn('id', $userIds)->get();

        $notificationData = [
            'type' => $type,
            'message' => $message,
            'sent_at' => now(),
        ];

        foreach ($users as $user) {
            $user->notify(new DocumentStatusChanged($notificationData));
        }
    }

    /**
     * Send system maintenance notification.
     */
    public function sendMaintenanceNotification(string $message, \DateTime $scheduledTime): void
    {
        $users = User::active()->administrators()->get();

        $notificationData = [
            'type' => 'maintenance',
            'message' => $message,
            'scheduled_time' => $scheduledTime,
        ];

        foreach ($users as $user) {
            $user->notify(new DocumentStatusChanged($notificationData));
            
            // Always send email for maintenance notifications
            Mail::to($user->email)->send(new DocumentApprovalNotification(null, 'maintenance', $notificationData));
        }
    }

    /**
     * Get notification statistics.
     */
    public function getNotificationStatistics(): array
    {
        $totalNotifications = \DB::table('notifications')->count();
        $unreadNotifications = \DB::table('notifications')->whereNull('read_at')->count();
        $recentNotifications = \DB::table('notifications')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return [
            'total' => $totalNotifications,
            'unread' => $unreadNotifications,
            'recent' => $recentNotifications,
            'read_percentage' => $totalNotifications > 0 ? round((($totalNotifications - $unreadNotifications) / $totalNotifications) * 100, 2) : 0,
        ];
    }

    /**
     * Clean up old notifications.
     */
    public function cleanupOldNotifications(int $daysToKeep = 30): int
    {
        $deletedCount = \DB::table('notifications')
            ->where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();

        return $deletedCount;
    }
}