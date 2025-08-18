<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class DocumentStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject($this->getMailSubject())
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line($this->data['message']);

        // Add action button if document exists
        if (isset($this->data['document_id'])) {
            $mailMessage->action(
                'View Document',
                route('documents.show', $this->data['document_id'])
            );
        }

        return $mailMessage->line('Thank you for using our Document Control System!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->data['type'],
            'message' => $this->data['message'],
            'document_id' => $this->data['document_id'] ?? null,
            'document_title' => $this->data['document_title'] ?? null,
            'document_number' => $this->data['document_number'] ?? null,
            'action_url' => isset($this->data['document_id']) 
                ? route('documents.show', $this->data['document_id']) 
                : null,
            'icon' => $this->getNotificationIcon(),
            'color' => $this->getNotificationColor(),
            'additional_data' => $this->getAdditionalData(),
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Get mail subject based on notification type.
     */
    protected function getMailSubject(): string
    {
        return match ($this->data['type']) {
            'submitted' => 'Document Submitted for Review',
            'revision_requested' => 'Document Revision Requested',
            'verified' => 'Document Verified - Awaiting Approval',
            'approved' => 'Document Approved',
            'published' => 'Document Published',
            'rejected' => 'Document Rejected',
            'expiring' => 'Document Expiring Soon',
            'overdue_reviews' => 'Overdue Document Reviews',
            'maintenance' => 'System Maintenance Notification',
            default => 'Document Status Update',
        };
    }

    /**
     * Get notification icon based on type.
     */
    protected function getNotificationIcon(): string
    {
        return match ($this->data['type']) {
            'submitted' => 'heroicon-o-paper-airplane',
            'revision_requested' => 'heroicon-o-exclamation-triangle',
            'verified' => 'heroicon-o-check-badge',
            'approved' => 'heroicon-o-shield-check',
            'published' => 'heroicon-o-globe-alt',
            'rejected' => 'heroicon-o-x-circle',
            'expiring' => 'heroicon-o-clock',
            'overdue_reviews' => 'heroicon-o-exclamation-circle',
            'maintenance' => 'heroicon-o-wrench-screwdriver',
            default => 'heroicon-o-bell',
        };
    }

    /**
     * Get notification color based on type.
     */
    protected function getNotificationColor(): string
    {
        return match ($this->data['type']) {
            'submitted' => 'info',
            'revision_requested' => 'warning',
            'verified' => 'success',
            'approved' => 'primary',
            'published' => 'success',
            'rejected' => 'danger',
            'expiring' => 'warning',
            'overdue_reviews' => 'danger',
            'maintenance' => 'gray',
            default => 'info',
        };
    }

    /**
     * Get additional data for the notification.
     */
    protected function getAdditionalData(): array
    {
        $additionalData = [];

        // Add revision notes if available
        if (isset($this->data['revision_notes'])) {
            $additionalData['revision_notes'] = $this->data['revision_notes'];
        }

        // Add rejection reason if available
        if (isset($this->data['rejection_reason'])) {
            $additionalData['rejection_reason'] = $this->data['rejection_reason'];
        }

        // Add expiry information if available
        if (isset($this->data['days_until_expiry'])) {
            $additionalData['days_until_expiry'] = $this->data['days_until_expiry'];
        }

        // Add overdue documents count if available
        if (isset($this->data['count'])) {
            $additionalData['overdue_count'] = $this->data['count'];
        }

        // Add documents list for overdue reviews
        if (isset($this->data['documents'])) {
            $additionalData['documents'] = $this->data['documents'];
        }

        // Add maintenance information if available
        if (isset($this->data['scheduled_time'])) {
            $additionalData['scheduled_time'] = $this->data['scheduled_time'];
        }

        return $additionalData;
    }

    /**
     * Determine if the notification should be sent immediately.
     */
    public function shouldSend(object $notifiable): bool
    {
        // Don't send notifications to inactive users
        if (!$notifiable->is_active) {
            return false;
        }

        // Always send urgent notifications immediately
        $urgentTypes = ['rejected', 'expiring', 'overdue_reviews', 'maintenance'];
        if (in_array($this->data['type'], $urgentTypes)) {
            return true;
        }

        return true;
    }

    /**
     * Get the notification priority.
     */
    public function getPriority(): string
    {
        return match ($this->data['type']) {
            'rejected', 'overdue_reviews', 'maintenance' => 'high',
            'expiring', 'revision_requested' => 'medium',
            default => 'normal',
        };
    }

    /**
     * Get the notification's tags for queuing.
     */
    public function tags(): array
    {
        $tags = ['document-notification', $this->data['type']];

        if (isset($this->data['document_id'])) {
            $tags[] = 'document-' . $this->data['document_id'];
        }

        return $tags;
    }
}