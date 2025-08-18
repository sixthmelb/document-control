<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPERADMIN = 'superadmin';
    case ADMIN = 'admin';
    case USER = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Super Administrator',
            self::ADMIN => 'Administrator',
            self::USER => 'User',
        };
    }

    public function getPermissions(): array
    {
        return match ($this) {
            self::SUPERADMIN => [
                'view_any_document',
                'create_document',
                'update_any_document',
                'delete_any_document',
                'approve_document',
                'publish_document',
                'manage_users',
                'manage_departments',
                'view_analytics',
                'manage_system_settings',
            ],
            self::ADMIN => [
                'view_any_document',
                'create_document',
                'update_document',
                'review_document',
                'verify_document',
                'view_department_analytics',
            ],
            self::USER => [
                'view_own_document',
                'create_document',
                'update_own_document',
                'view_published_document',
            ],
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'danger',
            self::ADMIN => 'warning',
            self::USER => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'heroicon-o-shield-exclamation',
            self::ADMIN => 'heroicon-o-user-group',
            self::USER => 'heroicon-o-user',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Full system access and final approval authority',
            self::ADMIN => 'Document review and verification authority',
            self::USER => 'Basic document creation and viewing access',
        };
    }

    public function canApprove(): bool
    {
        return $this === self::SUPERADMIN;
    }

    public function canReview(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMIN]);
    }

    public function canManageUsers(): bool
    {
        return $this === self::SUPERADMIN;
    }

    public function canAccessFilament(): bool
    {
        return in_array($this, [self::SUPERADMIN, self::ADMIN]);
    }

    public static function getSelectOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($role) => [$role->value => $role->getLabel()])
            ->toArray();
    }
}