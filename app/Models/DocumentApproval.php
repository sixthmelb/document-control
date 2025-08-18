<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentApproval extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'document_revision_id',
        'previous_status',
        'new_status',
        'action',
        'user_id',
        'user_role',
        'comments',
        'revision_notes',
        'checklist',
        'ip_address',
        'user_agent',
        'additional_data',
    ];

    protected $casts = [
        'previous_status' => DocumentStatus::class,
        'new_status' => DocumentStatus::class,
        'checklist' => 'array',
        'additional_data' => 'array',
    ];

    /**
     * Get the document this approval belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the document revision this approval belongs to.
     */
    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class);
    }

    /**
     * Get the user who performed this action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the action icon.
     */
    public function getActionIconAttribute(): string
    {
        return match ($this->action) {
            'submitted' => 'heroicon-o-paper-airplane',
            'reviewed' => 'heroicon-o-eye',
            'requested_revision' => 'heroicon-o-exclamation-triangle',
            'verified' => 'heroicon-o-check-badge',
            'approved' => 'heroicon-o-shield-check',
            'published' => 'heroicon-o-globe-alt',
            'rejected' => 'heroicon-o-x-circle',
            default => 'heroicon-o-pencil',
        };
    }

    /**
     * Get the action color.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'submitted' => 'info',
            'reviewed' => 'warning',
            'requested_revision' => 'danger',
            'verified' => 'success',
            'approved' => 'primary',
            'published' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get formatted action label.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'submitted' => 'Submitted for Review',
            'reviewed' => 'Started Review',
            'requested_revision' => 'Requested Revision',
            'verified' => 'Verified Document',
            'approved' => 'Approved Document',
            'published' => 'Published Document',
            'rejected' => 'Rejected Document',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }

    /**
     * Check if this action resulted in status progression.
     */
    public function isProgression(): bool
    {
        $progressionOrder = [
            DocumentStatus::DRAFT,
            DocumentStatus::SUBMITTED,
            DocumentStatus::UNDER_REVIEW,
            DocumentStatus::VERIFIED,
            DocumentStatus::APPROVED,
            DocumentStatus::PUBLISHED,
        ];

        $previousIndex = array_search($this->previous_status, $progressionOrder);
        $newIndex = array_search($this->new_status, $progressionOrder);

        return $newIndex > $previousIndex;
    }

    /**
     * Check if this action resulted in status regression.
     */
    public function isRegression(): bool
    {
        return in_array($this->new_status, [
            DocumentStatus::NEEDS_REVISION,
            DocumentStatus::REJECTED,
        ]);
    }

    /**
     * Get the time taken since previous action.
     */
    public function getTimeSincePreviousAction(): ?int
    {
        $previousApproval = self::where('document_id', $this->document_id)
            ->where('created_at', '<', $this->created_at)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$previousApproval) {
            return null;
        }

        return $this->created_at->diffInMinutes($previousApproval->created_at);
    }

    /**
     * Scope for specific document.
     */
    public function scopeForDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Scope for specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for specific action.
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for progressions only.
     */
    public function scopeProgressions($query)
    {
        return $query->whereNotIn('new_status', [
            DocumentStatus::NEEDS_REVISION,
            DocumentStatus::REJECTED,
        ]);
    }

    /**
     * Scope for regressions only.
     */
    public function scopeRegressions($query)
    {
        return $query->whereIn('new_status', [
            DocumentStatus::NEEDS_REVISION,
            DocumentStatus::REJECTED,
        ]);
    }

    /**
     * Scope for recent actions.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}