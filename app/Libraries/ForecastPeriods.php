<?php

namespace App\Libraries;

/**
 * Decides which sales periods are complete enough to learn a forecast from.
 *
 * A period is complete only when the forecast range covers all of it and it
 * has already ended. Today's day, this week and this month are still being
 * sold, and a week or month that the range cuts through is missing sales, so
 * either would read as an artificial drop if treated as a full observation.
 * Such periods can only sit at the two ends of the series.
 */
final class ForecastPeriods
{
    /** First day of a period key: Y-m-d, ISO "o-Www" or Y-m. */
    public static function start(string $key, string $interval): ?\DateTimeImmutable
    {
        try {
            if ($interval === 'weekly') {
                if (!preg_match('/^(\d{4})-W(\d{2})$/', $key, $m)) return null;
                return (new \DateTimeImmutable('today'))->setISODate((int) $m[1], (int) $m[2])->setTime(0, 0);
            }
            if ($interval === 'monthly') {
                return preg_match('/^\d{4}-\d{2}$/', $key) ? new \DateTimeImmutable($key . '-01') : null;
            }
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $key) ? new \DateTimeImmutable($key) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function end(\DateTimeImmutable $start, string $interval): \DateTimeImmutable
    {
        return match ($interval) {
            'weekly' => $start->modify('+6 days'),
            'monthly' => $start->modify('last day of this month'),
            default => $start,
        };
    }

    public static function isComplete(string $key, string $interval, string $rangeFrom, string $rangeTo, string $today): bool
    {
        $start = self::start($key, $interval);
        if ($start === null) return false;
        $end = self::end($start, $interval);

        return $start->format('Y-m-d') >= $rangeFrom
            && $end->format('Y-m-d') <= $rangeTo
            && $end->format('Y-m-d') < $today;
    }

    /**
     * Inclusive index span of the complete periods, or null when none is
     * complete (callers then fit every period, as there is nothing better).
     *
     * @param string[] $keys Period keys in chronological order.
     * @return array{first:int,last:int}|null
     */
    public static function usableSpan(array $keys, string $interval, string $rangeFrom, string $rangeTo, ?string $today = null): ?array
    {
        $today ??= date('Y-m-d');
        $first = null;
        $last = null;
        foreach (array_values($keys) as $i => $key) {
            if (self::isComplete((string) $key, $interval, $rangeFrom, $rangeTo, $today)) {
                $first ??= $i;
                $last = $i;
            }
        }

        return $first === null ? null : ['first' => $first, 'last' => $last];
    }
}
