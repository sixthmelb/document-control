<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Enums\DocumentStatus;
use Illuminate\Auth\Access\Response;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin() || $user->isUser();
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(User $user, Document $document): bool
    {
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

        // Users can view published documents in their department even if confidential
        if ($document->isPublished() && 
            $document->is_confidential && 
            $document->department_id === $user->department_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(User $user): bool
    {
        return $user->is_active && $user->hasPermissionTo('create_document');
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(User $user, Document $document): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Can't edit published or archived documents
        if (in_array($document->status, [DocumentStatus::PUBLISHED, DocumentStatus::ARCHIVED])) {
            return false;
        }

        // Superadmin can edit any non-published document
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can edit documents in their department if in review stages
        if ($user->isAdmin() && 
            $document->department_id === $user->department_id &&
            in_array($document->status, [
                DocumentStatus::SUBMITTED, 
                DocumentStatus::UNDER_REVIEW,
                DocumentStatus::VERIFIED
            ])) {
            return true;
        }

        // Users can edit their own documents in draft or needs revision
        if ($document->creator_id === $user->id && 
            in_array($document->status, [
                DocumentStatus::DRAFT, 
                DocumentStatus::NEEDS_REVISION
            ])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(User $user, Document $document): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Can't delete published documents
        if ($document->isPublished()) {
            return false;
        }

        // Superadmin can delete any non-published document
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Users can delete their own documents in draft status
        if ($document->creator_id === $user->id && 
            $document->status === DocumentStatus::DRAFT) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can submit the document for review.
     */
    public function submit(User $user, Document $document): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Only creator can submit their own documents
        if ($document->creator_id !== $user->id) {
            return false;
        }

        // Can only submit draft or revision-needed documents
        return in_array($document->status, [
            DocumentStatus::DRAFT, 
            DocumentStatus::NEEDS_REVISION
        ]);
    }

    /**
     * Determine whether the user can review the document.
     */
    public function review(User $user, Document $document): bool
    {
        if (!$user->is_active || !$user->canReview()) {
            return false;
        }

        // Superadmin can review any document
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can review documents in their department
        if ($user->isAdmin() && $document->department_id === $user->department_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can verify the document.
     */
    public function verify(User $user, Document $document): bool
    {
        if (!$user->canReview() || $document->status !== DocumentStatus::UNDER_REVIEW) {
            return false;
        }

        return $this->review($user, $document);
    }

    /**
     * Determine whether the user can approve the document.
     */
    public function approve(User $user, Document $document): bool
    {
        if (!$user->is_active || !$user->canApprove()) {
            return false;
        }

        // Only superadmin can approve documents
        return $user->isSuperAdmin() && $document->status === DocumentStatus::VERIFIED;
    }

    /**
     * Determine whether the user can publish the document.
     */
    public function publish(User $user, Document $document): bool
    {
        if (!$user->is_active || !$user->canApprove()) {
            return false;
        }

        // Only superadmin can publish approved documents
        return $user->isSuperAdmin() && $document->status === DocumentStatus::APPROVED;
    }

    /**
     * Determine whether the user can reject the document.
     */
    public function reject(User $user, Document $document): bool
    {
        if (!$user->is_active || !$user->canReview()) {
            return false;
        }

        // Can reject documents that are submitted, under review, or verified
        if (!in_array($document->status, [
            DocumentStatus::SUBMITTED,
            DocumentStatus::UNDER_REVIEW,
            DocumentStatus::VERIFIED
        ])) {
            return false;
        }

        return $this->review($user, $document);
    }

    /**
     * Determine whether the user can request revision for the document.
     */
    public function requestRevision(User $user, Document $document): bool
    {
        if (!$user->is_active || !$user->canReview()) {
            return false;
        }

        // Can request revision for documents under review
        if ($document->status !== DocumentStatus::UNDER_REVIEW) {
            return false;
        }

        return $this->review($user, $document);
    }

    /**
     * Determine whether the user can download the document.
     */
    public function download(User $user, Document $document): bool
    {
        // Same permissions as view
        return $this->view($user, $document);
    }

    /**
     * Determine whether the user can archive the document.
     */
    public function archive(User $user, Document $document): bool
    {
        if (!$user->is_active || !$user->canApprove()) {
            return false;
        }

        // Only superadmin can archive published documents
        return $user->isSuperAdmin() && $document->status === DocumentStatus::PUBLISHED;
    }

    /**
     * Determine whether the user can restore the document.
     */
    public function restore(User $user, Document $document): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the document.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can manage QR codes for the document.
     */
    public function manageQrCode(User $user, Document $document): bool
    {
        if (!$user->is_active || !$document->isPublished()) {
            return false;
        }

        // Superadmin can manage QR codes for all published documents
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can manage QR codes for documents in their department
        if ($user->isAdmin() && $document->department_id === $user->department_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view document statistics.
     */
    public function viewStatistics(User $user, Document $document): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Superadmin can view all statistics
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can view statistics for documents in their department
        if ($user->isAdmin() && $document->department_id === $user->department_id) {
            return true;
        }

        // Users can view statistics for their own documents
        if ($document->creator_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the document's audit trail.
     */
    public function viewAuditTrail(User $user, Document $document): bool
    {
        if (!$user->is_active) {
            return false;
        }

        // Superadmin can view all audit trails
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can view audit trails for documents in their department
        if ($user->isAdmin() && $document->department_id === $user->department_id) {
            return true;
        }

        // Users can view audit trails for their own documents
        if ($document->creator_id === $user->id) {
            return true;
        }

        return false;
    }
}