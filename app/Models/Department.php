<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the sections for the department.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /**
     * Get the active sections for the department.
     */
    public function activeSections(): HasMany
    {
        return $this->sections()->where('is_active', true);
    }

    /**
     * Get the users in this department.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the active users in this department.
     */
    public function activeUsers(): HasMany
    {
        return $this->users()->where('is_active', true);
    }

    /**
     * Get the documents created by this department.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get published documents from this department.
     */
    public function publishedDocuments(): HasMany
    {
        return $this->documents()->where('status', 'published');
    }

    /**
     * Get all documents through sections.
     */
    public function allDocuments(): HasManyThrough
    {
        return $this->hasManyThrough(Document::class, Section::class);
    }

    /**
     * Generate next document number for this department.
     */
    public function generateDocumentNumber(Section $section): string
    {
        $companyCode = config('app.company_code', 'AKM');
        $year = now()->year;
        $month = now()->format('m');
        
        // Get the latest document number for this department and section
        $latestDocument = Document::where('department_id', $this->id)
            ->where('section_id', $section->id)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', now()->month)
            ->orderBy('id', 'desc')
            ->first();
        
        $nextNumber = 1;
        if ($latestDocument) {
            // Extract the last number from document_number
            $parts = explode('-', $latestDocument->document_number);
            $lastNumber = (int) end($parts);
            $nextNumber = $lastNumber + 1;
        }
        
        return sprintf(
            '%s-%s-%s-%s-%s-%04d',
            $companyCode,
            $this->code,
            $section->code,
            $year,
            $month,
            $nextNumber
        );
    }

    /**
     * Get document statistics for this department.
     */
    public function getDocumentStats(): array
    {
        $documents = $this->documents();
        
        return [
            'total' => $documents->count(),
            'draft' => $documents->where('status', 'draft')->count(),
            'submitted' => $documents->where('status', 'submitted')->count(),
            'under_review' => $documents->where('status', 'under_review')->count(),
            'verified' => $documents->where('status', 'verified')->count(),
            'approved' => $documents->where('status', 'approved')->count(),
            'published' => $documents->where('status', 'published')->count(),
        ];
    }

    /**
     * Scope for active departments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the department's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * Check if department can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->documents()->count() === 0 && 
               $this->users()->count() === 0;
    }
}