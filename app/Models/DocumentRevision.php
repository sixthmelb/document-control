<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DocumentRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'version',
        'status',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'file_hash',
        'revision_notes',
        'changes_summary',
        'created_by',
        'reviewed_by',
        'submitted_at',
        'reviewed_at',
        'approved_at',
    ];

    protected $casts = [
        'status' => DocumentStatus::class,
        'file_size' => 'integer',
        'changes_summary' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the document this revision belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user who created this revision.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who reviewed this revision.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get file URL for this revision.
     */
    public function getFileUrlAttribute(): string
    {
        return Storage::url($this->file_path);
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
     * Get version type (major or minor).
     */
    public function getVersionTypeAttribute(): string
    {
        $parts = explode('.', $this->version);
        
        if (count($parts) >= 2) {
            $minor = (int) $parts[1];
            return $minor === 0 ? 'major' : 'minor';
        }
        
        return 'major';
    }

    /**
     * Check if this is the latest revision.
     */
    public function isLatest(): bool
    {
        $latestRevision = $this->document->revisions()->latest()->first();
        return $latestRevision && $latestRevision->id === $this->id;
    }

    /**
     * Generate next version number.
     */
    public static function generateNextVersion(Document $document, bool $isMajor = false): string
    {
        $latestRevision = $document->revisions()->latest()->first();
        
        if (!$latestRevision) {
            return '1.0';
        }
        
        $currentVersion = $latestRevision->version;
        $parts = explode('.', $currentVersion);
        $major = (int) ($parts[0] ?? 1);
        $minor = (int) ($parts[1] ?? 0);
        
        if ($isMajor) {
            $major++;
            $minor = 0;
        } else {
            $minor++;
        }
        
        return "{$major}.{$minor}";
    }

    /**
     * Create revision from document.
     */
    public static function createFromDocument(Document $document, User $creator, ?string $revisionNotes = null, bool $isMajor = false): self
    {
        $version = self::generateNextVersion($document, $isMajor);
        
        return self::create([
            'document_id' => $document->id,
            'version' => $version,
            'status' => $document->status,
            'original_filename' => $document->original_filename,
            'file_path' => $document->file_path,
            'file_type' => $document->file_type,
            'file_size' => $document->file_size,
            'file_hash' => $document->file_hash,
            'revision_notes' => $revisionNotes,
            'created_by' => $creator->id,
            'submitted_at' => $document->submitted_at,
            'reviewed_at' => $document->reviewed_at,
            'approved_at' => $document->approved_at,
        ]);
    }

    /**
     * Scope for specific document.
     */
    public function scopeForDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Scope for specific status.
     */
    public function scopeByStatus($query, DocumentStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for major versions only.
     */
    public function scopeMajorVersions($query)
    {
        return $query->where('version', 'like', '%.0');
    }

    /**
     * Scope for minor versions only.
     */
    public function scopeMinorVersions($query)
    {
        return $query->where('version', 'not like', '%.0');
    }
}