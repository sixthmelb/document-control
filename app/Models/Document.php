<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\DocumentApproval;
use App\Models\DocumentDownload;
use App\Models\DocumentRevision;
use App\Models\Department;
use App\Models\Section;
use App\Models\User;
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
        'title',
        'description',
        'document_number',
        'version',
        'status',
        'department_id',
        'section_id',
        'creator_id',
        'reviewer_id',
        'approver_id',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'file_hash',
        'qr_code_path',
        'qr_code_token',
        'is_confidential',
        'effective_date',
        'expiry_date',
        'published_at',
        'review_deadline',
        'approval_deadline',
        'tags',
        'view_count',
        'download_count',
    ];

    protected $casts = [
        'status' => DocumentStatus::class,
        'is_confidential' => 'boolean',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'published_at' => 'datetime',
        'review_deadline' => 'datetime',
        'approval_deadline' => 'datetime',
        'tags' => 'array',
        'view_count' => 'integer',
        'download_count' => 'integer',
    ];

    protected $appends = ['formatted_file_size'];

    /**
     * Relationships
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(DocumentDownload::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class);
    }

    /**
     * Scopes
     */
    public function scopePubliclyAccessible($query)
    {
        return $query->where('status', DocumentStatus::PUBLISHED)
                    ->where('is_confidential', false);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'LIKE', "%{$search}%")
              ->orWhere('description', 'LIKE', "%{$search}%")
              ->orWhere('document_number', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Status check methods
     */
    public function isPublished(): bool
    {
        return $this->status === DocumentStatus::PUBLISHED;
    }

    public function isPubliclyAccessible(): bool
    {
        return $this->isPublished() && !$this->is_confidential;
    }

    public function hasFile(): bool
    {
        return !empty($this->file_path) && !empty($this->original_filename);
    }

    public function canBeSubmitted(): bool
    {
        return $this->hasFile() && 
               in_array($this->status, [DocumentStatus::DRAFT, DocumentStatus::NEEDS_REVISION]);
    }

    public function canBeEditedBy(User $user): bool
    {
        // Super admin can edit any document
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Creator can edit if not published
        if ($this->creator_id === $user->id && !$this->isPublished()) {
            return true;
        }

        // Admin can edit documents in their department if not published
        if ($user->isAdmin() && 
            $user->department_id === $this->department_id && 
            !$this->isPublished()) {
            return true;
        }

        return false;
    }

    /**
     * QR Code methods
     */
    public function hasQrCode(): bool
    {
        return !empty($this->qr_code_path) && !empty($this->qr_code_token);
    }

    public function getQrCodeUrlAttribute(): ?string
    {
        if (!$this->hasQrCode()) {
            return null;
        }

        if (Storage::disk('qrcodes')->exists($this->qr_code_path)) {
            return Storage::disk('qrcodes')->url($this->qr_code_path);
        }

        return null;
    }

    public function getQrValidationUrlAttribute(): ?string
    {
        if (!$this->hasQrCode()) {
            return null;
        }

        return route('qr.validate', [
            'document' => $this->id,
            'token' => $this->qr_code_token
        ]);
    }

    /**
     * File methods
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->hasFile()) {
            return null;
        }

        if ($this->isPublished()) {
            return route('public.documents.download', $this->id);
        }
        
        return route('documents.download', $this->id);
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '0 B';
        }

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
            $companyCode = config('app.company_code', 'AKM');
            
            $department = $this->department ?? Department::find($this->department_id);
            $section = $this->section ?? Section::find($this->section_id);
            
            $deptCode = $department?->code ?? 'UNKN';
            $sectCode = $section?->code ?? 'UNKN';
            
            $year = now()->format('Y');
            $month = now()->format('m');
            
            // Get next number for this department/section/year/month
            $lastNumber = static::where('department_id', $this->department_id)
                ->where('section_id', $this->section_id)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->whereNotNull('document_number')
                ->count();
            
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
            
            return "{$companyCode}-{$deptCode}-{$sectCode}-{$year}-{$month}-{$nextNumber}";
            
        } catch (\Exception $e) {
            \Log::error('Document number generation failed: ' . $e->getMessage());
            return 'AUTO-' . now()->format('YmdHis');
        }
    }

    /**
     * Update document status with approval tracking.
     */
    public function updateStatus(DocumentStatus $newStatus, User $user, ?string $comments = null): bool
    {
        try {
            $oldStatus = $this->status;
            $this->status = $newStatus;
            
            // Set timestamps based on status
            if ($newStatus === DocumentStatus::PUBLISHED) {
                $this->published_at = now();
            }
            
            $this->save();
            
            // Create approval record
            DocumentApproval::create([
                'document_id' => $this->id,
                'user_id' => $user->id,
                'status' => $newStatus->value,
                'previous_status' => $oldStatus?->value,
                'comments' => $comments,
                'approved_at' => now(),
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            \Log::error("Status update failed for document {$this->id}: " . $e->getMessage());
            return false;
        }
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
     * Boot method for model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($document) {
            // Auto-generate document number if not set
            if (empty($document->document_number)) {
                $document->document_number = $document->generateDocumentNumber();
            }
        });
    }
}