<?php

namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ExpiryDatePolicy
{
    private static function calendarDate(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/^(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** Browser and server use the same Philippine calendar-year boundary. */
    public static function limits(?string $today = null): array
    {
        $date = $today === null
            ? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'))
            : self::calendarDate($today);
        if (!$date) throw new InvalidArgumentException('The expiry policy requires a valid current date.');
        $years = config(\Config\InventoryExpiry::class)->yearsAhead;
        if ($years < 1 || $years > 100) $years = 10;
        $year = min(9999, (int)$date->format('Y') + $years);
        return ['today' => $date->format('Y-m-d'), 'minimum' => $date->modify('+1 day')->format('Y-m-d'),
            'maximum' => sprintf('%04d-12-31', $year), 'yearsAhead' => $years];
    }

    /** Unchanged stored dates are retained; every new or changed date has the same limit. */
    public static function assertAllowed(?string $value, ?string $original = null, ?string $today = null): void
    {
        $value = trim($value ?? '');
        if ($value === '') return;
        if (!self::calendarDate($value)) throw new InvalidArgumentException('Enter a valid expiration date in YYYY-MM-DD format.');
        if ($original !== null && $value === $original) return;
        $limits = self::limits($today);
        if ($value < $limits['minimum']) {
            throw new InvalidArgumentException('New or changed stock expiry must be after today. Items expiring today are already expired.');
        }
        if ($value > $limits['maximum']) {
            throw new InvalidArgumentException('Expiration date must be on or before ' . $limits['maximum'] . '.');
        }
    }
}
