<?php

namespace App\Mail;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DocumentApprovalNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ?Document $document;
    public string $type;
    public array $additionalData;

    /**
     * Create a new message instance.
     */
    public function __construct(?Document $document, string $type, array $additionalData = [])
    {
        $this->document = $document;
        $this->type = $type;
        $this->additionalData = $additionalData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getSubject(),
            tags: $this->getTags(),
            metadata: [
                'document_id' => $this->document?->id,
                'notification_type' => $this->type,
            ],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->getViewName(),
            with: [
                'document' => $this->document,
                'type' => $this->type,
                'additionalData' => $this->additionalData,
                'actionUrl' => $this->getActionUrl(),
                'actionText' => $this->getActionText(),
                'greeting' => $this->getGreeting(),
                'message' => $this->getMessage(),
                'instructions' => $this->getInstructions(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get email subject based on type.
     */
    protected function getSubject(): string
    {
        if (!$this->document) {
            return match ($this->type) {
                'overdue_reviews' => 'Overdue Document Reviews Require Attention',
                'maintenance' => 'System Maintenance Notification',
                default => 'Document Control System Notification',
            };
        }

        $docTitle = $this->document->title;
        $docNumber = $this->document->document_number;

        return match ($this->type) {
            'submitted' => "Document Submitted for Review: {$docTitle} ({$docNumber})",
            'revision_requested' => "Revision Requested: {$docTitle} ({$docNumber})",
            'verified' => "Document Verified - Awaiting Approval: {$docTitle} ({$docNumber})",
            'approved' => "Document Approved: {$docTitle} ({$docNumber})",
            'published' => "Document Published: {$docTitle} ({$docNumber})",
            'published_external' => "New Document Available: {$docTitle}",
            'rejected' => "Document Rejected: {$docTitle} ({$docNumber})",
            'expiring' => "Document Expiring Soon: {$docTitle} ({$docNumber})",
            default => "Document Status Update: {$docTitle} ({$docNumber})",
        };
    }

    /**
     * Get view name based on type.
     */
    protected function getViewName(): string
    {
        return match ($this->type) {
            'published_external' => 'emails.document.published-external',
            'overdue_reviews' => 'emails.document.overdue-reviews',
            'maintenance' => 'emails.system.maintenance',
            default => 'emails.document.status-notification',
        };
    }

    /**
     * Get greeting message.
     */
    protected function getGreeting(): string
    {
        return match ($this->type) {
            'published_external' => 'Dear Stakeholder,',
            'maintenance' => 'Dear System Administrator,',
            'overdue_reviews' => 'Dear Reviewer,',
            default => 'Hello,',
        };
    }

    /**
     * Get main message content.
     */
    protected function getMessage(): string
    {
        if (!$this->document) {
            return match ($this->type) {
                'overdue_reviews' => 'You have overdue document reviews that require your attention.',
                'maintenance' => 'A system maintenance is scheduled. Please review the details below.',
                default => 'This is a notification from the Document Control System.',
            };
        }

        $docTitle = $this->document->title;
        $docNumber = $this->document->document_number;

        return match ($this->type) {
            'submitted' => "The document \"{$docTitle}\" ({$docNumber}) has been submitted for review and requires your attention.",
            'revision_requested' => "The document \"{$docTitle}\" ({$docNumber}) requires revision. Please review the comments and make necessary changes.",
            'verified' => "The document \"{$docTitle}\" ({$docNumber}) has been verified and is now awaiting final approval.",
            'approved' => "Good news! Your document \"{$docTitle}\" ({$docNumber}) has been approved.",
            'published' => "Your document \"{$docTitle}\" ({$docNumber}) has been published and is now available to the public.",
            'published_external' => "A new document \"{$docTitle}\" is now available and may be of interest to you.",
            'rejected' => "Unfortunately, the document \"{$docTitle}\" ({$docNumber}) has been rejected. Please review the feedback provided.",
            'expiring' => "The document \"{$docTitle}\" ({$docNumber}) is approaching its expiry date and may need to be reviewed or updated.",
            default => "The status of document \"{$docTitle}\" ({$docNumber}) has been updated.",
        };
    }

    /**
     * Get action URL.
     */
    protected function getActionUrl(): ?string
    {
        if (!$this->document) {
            return match ($this->type) {
                'overdue_reviews' => route('documents.pending-review'),
                default => null,
            };
        }

        return match ($this->type) {
            'published_external' => route('public.documents.show', $this->document),
            default => route('documents.show', $this->document),
        };
    }

    /**
     * Get action button text.
     */
    protected function getActionText(): string
    {
        if (!$this->document) {
            return match ($this->type) {
                'overdue_reviews' => 'View Pending Reviews',
                default => 'View System',
            };
        }

        return match ($this->type) {
            'submitted', 'verified' => 'Review Document',
            'revision_requested', 'rejected' => 'View Document & Feedback',
            'published_external' => 'View Document',
            'expiring' => 'Review Document',
            default => 'View Document',
        };
    }

    /**
     * Get additional instructions.
     */
    protected function getInstructions(): ?string
    {
        return match ($this->type) {
            'submitted' => 'Please review the document and either approve it, request revisions, or reject it with appropriate comments.',
            'revision_requested' => 'Please review the revision notes carefully and make the necessary changes before resubmitting.',
            'verified' => 'This document has been verified by an administrator and now requires your final approval for publication.',
            'rejected' => 'Please review the rejection reason and make necessary improvements before resubmitting.',
            'expiring' => 'Please verify if this document is still current and update the expiry date if needed.',
            'published_external' => 'You can access this document directly from our public document portal.',
            'overdue_reviews' => 'Please prioritize these reviews to ensure timely document processing.',
            default => null,
        };
    }

    /**
     * Get email tags for tracking.
     */
    protected function getTags(): array
    {
        $tags = ['document-control', $this->type];
        
        if ($this->document) {
            $tags[] = 'document-' . $this->document->id;
            $tags[] = 'department-' . $this->document->department_id;
        }

        return $tags;
    }

    /**
     * Determine the time to send the email.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    /**
     * Calculate the number of seconds to wait before retrying.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 min, 5 min, 15 min
    }
}