<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Account roles. Mirrors the Laravel App\Enums\Role enum.
 */
enum Role: string
{
    case Client = 'client';
    case Manager = 'manager';
    case Admin = 'admin';

    /**
     * Managers and admins can operate on gym-wide data.
     */
    public function isStaff(): bool
    {
        return $this === self::Manager || $this === self::Admin;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Symfony security role name for this application role.
     */
    public function securityRole(): string
    {
        return 'ROLE_'.strtoupper($this->value);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
