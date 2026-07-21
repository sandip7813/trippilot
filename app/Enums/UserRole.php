<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function isAdmin(): bool
    {
        return in_array($this, [self::Admin, self::SuperAdmin], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function label(): string
    {
        return match ($this) {
            self::User => 'User',
            self::Admin => 'Admin',
            self::SuperAdmin => 'Super Admin',
        };
    }

    /**
     * @return list<self>
     */
    public static function assignableBy(UserRole $actorRole, UserRole $targetRole): array
    {
        if ($actorRole === self::SuperAdmin) {
            return self::cases();
        }

        if ($actorRole === self::Admin && ! $targetRole->isSuperAdmin()) {
            return [self::User, self::Admin];
        }

        return [];
    }
}
