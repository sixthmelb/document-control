<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
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
     * Get the department that owns the section.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the users in this section.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the active users in this section.
     */
    public function activeUsers(): HasMany
    {
        return $this->users()->where('is_active', true);
    }

    /**
     * Get the documents created by this section.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get published documents from this section.
     */
    public function publishedDocuments(): HasMany
    {
        return $this->documents()->where('status', 'published');
    }

    /**
     * Get document statistics for this section.
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
     * Scope for active sections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sections in active departments.
     */
    public function scopeInActiveDepartments($query)
    {
        return $query->whereHas('department', function ($q) {
            $q->where('is_active', true);
        });
    }

    /**
     * Get the section's full path (Department - Section).
     */
    public function getFullPathAttribute(): string
    {
        return "{$this->department->code} - {$this->code}";
    }

    /**
     * Get the section's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->department->name} / {$this->name}";
    }

    /**
     * Get the section's full display name with codes.
     */
    public function getFullDisplayNameAttribute(): string
    {
        return "{$this->department->code}-{$this->code} ({$this->department->name} / {$this->name})";
    }

    /**
     * Check if section can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->documents()->count() === 0 && 
               $this->users()->count() === 0;
    }

    /**
     * Get sections by department for select options.
     */
    public static function getSelectOptionsByDepartment($departmentId = null): array
    {
        $query = self::with('department')
            ->active()
            ->inActiveDepartments()
            ->orderBy('name');
            
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        
        return $query->get()
            ->mapWithKeys(function ($section) {
                return [$section->id => $section->full_display_name];
            })
            ->toArray();
    }
}