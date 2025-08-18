<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case UNDER_REVIEW = 'under_review';
    case NEEDS_REVISION = 'needs_revision';
    case VERIFIED = 'verified';
    case APPROVED = 'approved';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::UNDER_REVIEW => 'Under Review',
            self::NEEDS_REVISION => 'Needs Revision',
            self::VERIFIED => 'Verified',
            self::APPROVED => 'Approved',
            self::PUBLISHED => 'Published',
            self::REJECTED => 'Rejected',
            self::ARCHIVED => 'Archived',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'info',
            self::UNDER_REVIEW => 'warning',
            self::NEEDS_REVISION => 'danger',
            self::VERIFIED => 'success',
            self::APPROVED => 'primary',
            self::PUBLISHED => 'success',
            self::REJECTED => 'danger',
            self::ARCHIVED => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-o-document',
            self::SUBMITTED => 'heroicon-o-paper-airplane',
            self::UNDER_REVIEW => 'heroicon-o-eye',
            self::NEEDS_REVISION => 'heroicon-o-exclamation-triangle',
            self::VERIFIED => 'heroicon-o-check-badge',
            self::APPROVED => 'heroicon-o-shield-check',
            self::PUBLISHED => 'heroicon-o-globe-alt',
            self::REJECTED => 'heroicon-o-x-circle',
            self::ARCHIVED => 'heroicon-o-archive-box',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::DRAFT => 'Document is being prepared',
            self::SUBMITTED => 'Document submitted for review',
            self::UNDER_REVIEW => 'Document is being reviewed by admin',
            self::NEEDS_REVISION => 'Document requires revision',
            self::VERIFIED => 'Document verified by admin',
            self::APPROVED => 'Document approved by superadmin',
            self::PUBLISHED => 'Document published and accessible to public',
            self::REJECTED => 'Document rejected',
            self::ARCHIVED => 'Document archived',
        };
    }

    public static function getNextAllowedStatuses(self $currentStatus): array
    {
        return match ($currentStatus) {
            self::DRAFT => [self::SUBMITTED],
            self::SUBMITTED => [self::UNDER_REVIEW, self::REJECTED],
            self::UNDER_REVIEW => [self::NEEDS_REVISION, self::VERIFIED],
            self::NEEDS_REVISION => [self::SUBMITTED],
            self::VERIFIED => [self::APPROVED, self::NEEDS_REVISION],
            self::APPROVED => [self::PUBLISHED],
            self::PUBLISHED => [self::ARCHIVED],
            self::REJECTED => [self::DRAFT],
            self::ARCHIVED => [],
        };
    }

    public function canBeEditedBy(string $userRole): bool
    {
        return match ($this) {
            self::DRAFT, self::NEEDS_REVISION => in_array($userRole, ['superadmin', 'admin', 'user']),
            self::SUBMITTED, self::UNDER_REVIEW => in_array($userRole, ['superadmin', 'admin']),
            self::VERIFIED => in_array($userRole, ['superadmin']),
            default => false,
        };
    }
}