<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_number',
        'title',
        'description',
        'document_type',
        'status',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'file_hash',
        'version',
        'effective_date',
        'expiry_date',
        'tags',
        'metadata',
        'creator_id',
        'department_id',
        'section_id',
        'current_reviewer_id',
        'approved_by',
        'submitted_at',
        'reviewed_at',
        'verified_at',
        'approved_at',
        'published_at',
        'qr_code_path',
        'validation_token',
        'is_confidential',
        'download_count',
        'view_count',
    ];

    /**
     * The "boot" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate document number and set creator when creating
        static::creating(function ($document) {
            // Set creator_id if not already set
            if (empty($document->creator_id) && auth()->check()) {
                $document->creator_id = auth()->id();
            }
            
            // Generate document number if not already set
            if (empty($document->document_number)) {
                $document->document_number = $document->generateDocumentNumber();
            }
            
            // Set default values for file fields if not provided (for draft documents)
            if (empty($document->original_filename)) {
                $document->original_filename = null;
            }
            if (empty($document->file_path)) {
                $document->file_path = null;
            }
            if (empty($document->file_type)) {
                $document->file_type = null;
            }
            if (empty($document->file_size)) {
                $document->file_size = null;
            }
            if (empty($document->file_hash)) {
                $document->file_hash = null;
            }
        });
    }
    protected $casts = [
        'status' => DocumentStatus::class,
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'tags' => 'array',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'is_confidential' => 'boolean',
        'file_size' => 'integer',
        'download_count' => 'integer',
        'view_count' => 'integer',
    ];

    /**
     * Get the user who created the document.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the current reviewer of the document.
     */
    public function currentReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_reviewer_id');
    }

    /**
     * Get the user who approved the document.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the department the document belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the section the document belongs to.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the document revisions.
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the document approvals (audit trail).
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the document downloads.
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(DocumentDownload::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest revision.
     */
    public function latestRevision()
    {
        return $this->revisions()->latest()->first();
    }

    /**
     * Check if document can be edited by user.
     */
    public function canBeEditedBy(User $user): bool
    {
        // Superadmin can edit everything except published documents
        if ($user->isSuperAdmin() && !$this->isPublished()) {
            return true;
        }

        // Admin can edit documents in review stages
        if ($user->isAdmin() && $this->status->canBeEditedBy('admin')) {
            return true;
        }

        // User can only edit their own documents in draft or needs revision
        if ($user->isUser() && 
            $this->creator_id === $user->id && 
            $this->status->canBeEditedBy('user')) {
            return true;
        }

        return false;
    }

    /**
     * Check if document can be reviewed by user.
     */
    public function canBeReviewedBy(User $user): bool
    {
        return $user->canReview() && 
               in_array($this->status, [DocumentStatus::SUBMITTED, DocumentStatus::UNDER_REVIEW]);
    }

    /**
     * Check if document can be approved by user.
     */
    public function canBeApprovedBy(User $user): bool
    {
        return $user->canApprove() && $this->status === DocumentStatus::VERIFIED;
    }

    /**
     * Check if document is published.
     */
    public function isPublished(): bool
    {
        return $this->status === DocumentStatus::PUBLISHED;
    }

    /**
     * Check if document is accessible to public.
     */
    public function isPubliclyAccessible(): bool
    {
        return $this->isPublished() && !$this->is_confidential;
    }

    /**
     * Check if document has file attached.
     */
    public function hasFile(): bool
    {
        return !empty($this->file_path) && !empty($this->original_filename);
    }

    /**
     * Check if document can be submitted (needs file).
     */
    public function canBeSubmitted(): bool
    {
        return $this->hasFile() && 
               in_array($this->status, [DocumentStatus::DRAFT, DocumentStatus::NEEDS_REVISION]);
    }

    /**
     * Get file URL.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->hasFile()) {
            return null;
        }

        if ($this->isPublished()) {
            return Storage::url($this->file_path);
        }
        
        return route('documents.download', $this->id);
    }

    /**
     * Get QR code URL.
     */
    public function getQrCodeUrlAttribute(): ?string
    {
        return $this->qr_code_path ? Storage::url($this->qr_code_path) : null;
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Generate document number automatically.
     */
    public function generateDocumentNumber(): string
    {
        try {
            // Get company code from config
            $companyCode = config('app.company_code', 'AKM');
            
            // Get department and section codes
            $department = $this->department ?? Department::find($this->department_id);
            $section = $this->section ?? Section::find($this->section_id);
            
            if (!$department || !$section) {
                throw new \Exception('Department and Section are required to generate document number');
            }
            
            $year = now()->year;
            $month = now()->format('m');
            
            // Get the latest document number for this department and section in current month
            $latestDocument = self::where('department_id', $this->department_id)
                ->where('section_id', $this->section_id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', now()->month)
                ->whereNotNull('document_number')
                ->orderBy('id', 'desc')
                ->first();
            
            $nextNumber = 1;
            if ($latestDocument && $latestDocument->document_number) {
                // Extract the last number from document_number
                $parts = explode('-', $latestDocument->document_number);
                if (count($parts) >= 6) {
                    $lastNumber = (int) end($parts);
                    $nextNumber = $lastNumber + 1;
                }
            }
            
            return sprintf(
                '%s-%s-%s-%s-%s-%04d',
                $companyCode,
                $department->code,
                $section->code,
                $year,
                $month,
                $nextNumber
            );
        } catch (\Exception $e) {
            \Log::error('Failed to generate document number', [
                'error' => $e->getMessage(),
                'department_id' => $this->department_id,
                'section_id' => $this->section_id,
            ]);
            
            // Fallback document number
            return sprintf(
                '%s-TEMP-%s-%04d',
                config('app.company_code', 'AKM'),
                now()->format('YmdHis'),
                rand(1000, 9999)
            );
        }
    }

    /**
     * Update document status with audit trail.
     */
    public function updateStatus(DocumentStatus $newStatus, User $user, ?string $comments = null): bool
    {
        $previousStatus = $this->status;
        
        // Create approval record
        $this->approvals()->create([
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'action' => $this->getActionFromStatus($newStatus),
            'user_id' => $user->id,
            'user_role' => $user->role->value,
            'comments' => $comments,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        // Update document
        $updateData = ['status' => $newStatus];
        
        // Set timestamps based on status
        switch ($newStatus) {
            case DocumentStatus::SUBMITTED:
                $updateData['submitted_at'] = now();
                break;
            case DocumentStatus::UNDER_REVIEW:
                $updateData['current_reviewer_id'] = $user->id;
                break;
            case DocumentStatus::VERIFIED:
                $updateData['reviewed_at'] = now();
                $updateData['verified_at'] = now();
                break;
            case DocumentStatus::APPROVED:
                $updateData['approved_at'] = now();
                $updateData['approved_by'] = $user->id;
                break;
            case DocumentStatus::PUBLISHED:
                $updateData['published_at'] = now();
                break;
        }

        return $this->update($updateData);
    }

    /**
     * Increment view count.
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Increment download count.
     */
    public function incrementDownloadCount(): void
    {
        $this->increment('download_count');
    }

    /**
     * Generate validation token for QR code.
     */
    public function generateValidationToken(): string
    {
        $token = hash('sha256', $this->id . $this->document_number . now()->timestamp);
        $this->update(['validation_token' => $token]);
        return $token;
    }

    /**
     * Scopes
     */
    public function scopePublished($query)
    {
        return $query->where('status', DocumentStatus::PUBLISHED);
    }

    public function scopeByStatus($query, DocumentStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeBySection($query, $sectionId)
    {
        return $query->where('section_id', $sectionId);
    }

    public function scopeByCreator($query, $creatorId)
    {
        return $query->where('creator_id', $creatorId);
    }

    public function scopePubliclyAccessible($query)
    {
        return $query->published()->where('is_confidential', false);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('document_number', 'like', "%{$search}%")
              ->orWhere('document_type', 'like', "%{$search}%");
        });
    }

    /**
     * Get action name from status.
     */
    private function getActionFromStatus(DocumentStatus $status): string
    {
        return match ($status) {
            DocumentStatus::SUBMITTED => 'submitted',
            DocumentStatus::UNDER_REVIEW => 'reviewed',
            DocumentStatus::NEEDS_REVISION => 'requested_revision',
            DocumentStatus::VERIFIED => 'verified',
            DocumentStatus::APPROVED => 'approved',
            DocumentStatus::PUBLISHED => 'published',
            DocumentStatus::REJECTED => 'rejected',
            default => 'updated',
        };
    }
}