<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Helpers that keep API date/decimal formatting identical to the Laravel
 * implementation (ISO-8601 timestamps and two-decimal money strings).
 */
final class DateFormatter
{
    public static function date(?\DateTimeInterface $value): ?string
    {
        return $value?->format('Y-m-d');
    }

    public static function dateTime(?\DateTimeInterface $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.000000\Z');
    }

    public static function decimal(null|string|int|float $value): ?string
    {
        return null === $value ? null : number_format((float) $value, 2, '.', '');
    }
}
