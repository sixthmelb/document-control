<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\Traits\HasRoles; // Add this trait

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, HasRoles; // Add HasRoles trait

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_id',
        'role',
        'department_id',
        'section_id',
        'phone',
        'address',
        'is_active',
        'last_login_at',
        'preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'preferences' => 'array',
        ];
    }

    /**
     * Determine if the user can access Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->role->canAccessFilament();
    }

    /**
     * Get the department that the user belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the section that the user belongs to.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get the documents created by this user.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'creator_id');
    }

    /**
     * Get the documents currently being reviewed by this user.
     */
    public function reviewingDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'current_reviewer_id');
    }

    /**
     * Get the documents approved by this user.
     */
    public function approvedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'approved_by');
    }

    /**
     * Get the document approvals made by this user.
     */
    public function documentApprovals(): HasMany
    {
        return $this->hasMany(DocumentApproval::class);
    }

    /**
     * Get the document downloads by this user.
     */
    public function documentDownloads(): HasMany
    {
        return $this->hasMany(DocumentDownload::class);
    }

    /**
     * Get the document revisions created by this user.
     */
    public function documentRevisions(): HasMany
    {
        return $this->hasMany(DocumentRevision::class, 'created_by');
    }

    /**
     * Check if user has specific role (custom method to avoid conflict with Spatie).
     */
    public function hasUserRole(UserRole $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPERADMIN;
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Check if user is regular user.
     */
    public function isUser(): bool
    {
        return $this->role === UserRole::USER;
    }

    /**
     * Check if user can approve documents.
     */
    public function canApprove(): bool
    {
        return $this->role->canApprove();
    }

    /**
     * Check if user can review documents.
     */
    public function canReview(): bool
    {
        return $this->role->canReview();
    }

    /**
     * Check if user can manage other users.
     */
    public function canManageUsers(): bool
    {
        return $this->role->canManageUsers();
    }

    /**
     * Get user's full organizational path.
     */
    public function getOrganizationalPathAttribute(): string
    {
        $path = [];
        
        if ($this->department) {
            $path[] = $this->department->name;
        }
        
        if ($this->section) {
            $path[] = $this->section->name;
        }
        
        return implode(' / ', $path);
    }

    /**
     * Get user's role label.
     */
    public function getRoleLabelAttribute(): string
    {
        return $this->role->getLabel();
    }

    /**
     * Scope for active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for users by role.
     */
    public function scopeByRole($query, UserRole $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope for administrators (admin and superadmin).
     */
    public function scopeAdministrators($query)
    {
        return $query->whereIn('role', [UserRole::ADMIN, UserRole::SUPERADMIN]);
    }

    /**
     * Scope for users in specific department.
     */
    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get user's document statistics.
     */
    public function getDocumentStats(): array
    {
        $documents = $this->documents();
        
        return [
            'total_created' => $documents->count(),
            'draft' => $documents->where('status', 'draft')->count(),
            'submitted' => $documents->where('status', 'submitted')->count(),
            'published' => $documents->where('status', 'published')->count(),
            'total_reviewed' => $this->documentApprovals()->count(),
            'total_downloads' => $this->documentDownloads()->count(),
        ];
    }
}