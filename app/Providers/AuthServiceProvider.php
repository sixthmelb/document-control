<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Document::class => DocumentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Define Gates for role-based access
        Gate::define('review-documents', function (User $user) {
            return $user->canReview();
        });

        Gate::define('approve-documents', function (User $user) {
            return $user->canApprove();
        });

        Gate::define('manage-users', function (User $user) {
            return $user->canManageUsers();
        });

        Gate::define('access-admin-panel', function (User $user) {
            return $user->isAdmin() || $user->isSuperAdmin();
        });

        Gate::define('manage-departments', function (User $user) {
            return $user->isSuperAdmin();
        });

        Gate::define('manage-sections', function (User $user) {
            return $user->isSuperAdmin();
        });

        Gate::define('view-analytics', function (User $user) {
            return $user->isAdmin() || $user->isSuperAdmin();
        });

        Gate::define('manage-qr-codes', function (User $user) {
            return $user->canReview();
        });

        // Document-specific gates
        Gate::define('submit-document', function (User $user, Document $document) {
            return $document->creator_id === $user->id && 
                   in_array($document->status->value, ['draft', 'needs_revision']) &&
                   $document->hasFile();
        });

        Gate::define('edit-document', function (User $user, Document $document) {
            return $document->canBeEditedBy($user);
        });

        Gate::define('delete-document', function (User $user, Document $document) {
            return !$document->isPublished() && 
                   ($user->isSuperAdmin() || $document->creator_id === $user->id);
        });

        Gate::define('view-document', function (User $user, Document $document) {
            // Superadmin can view all documents
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Admin can view documents in their department
            if ($user->isAdmin() && $document->department_id === $user->department_id) {
                return true;
            }

            // Users can view their own documents
            if ($document->creator_id === $user->id) {
                return true;
            }

            // Anyone can view published non-confidential documents
            if ($document->isPublished() && !$document->is_confidential) {
                return true;
            }

            return false;
        });

        Gate::define('download-document', function (User $user, Document $document) {
            return Gate::allows('view-document', $document);
        });
    }
}