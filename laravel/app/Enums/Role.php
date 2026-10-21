<?php

namespace App\Enums;

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
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
