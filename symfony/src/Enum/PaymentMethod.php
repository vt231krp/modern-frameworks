<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Online = 'online';
    case Transfer = 'transfer';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
