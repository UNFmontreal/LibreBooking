<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use DateTimeInterface;

/**
 * Decides whether reservations fall inside the working hours of the MRI.
 *
 * The hours are configured per ISO weekday (1 = Monday ... 7 = Sunday) as [start, end, start, end]: two windows
 * "HH:MM", where a second window is optional. A reservation must fit inside one window, on a single day.
 * A reservation that ends at midnight counts as ending at the end of the day.
 */
final class WorkingHours
{
    private const MINUTES_PER_DAY = 1440;

    /**
     * @param array<int, array<int, string>> $hours working hours per ISO weekday
     */
    public static function permits(DateTimeInterface $start, DateTimeInterface $end, array $hours): bool
    {
        $day = (int) $start->format('N');
        if ($day !== (int) $end->format('N') || !isset($hours[$day])) {
            return false;
        }

        $startMinute = self::minuteOfDay($start);
        $endMinute = self::minuteOfDay($end);
        $window = array_values($hours[$day]);

        return self::fits($startMinute, $endMinute, $window[0] ?? null, $window[1] ?? null)
            || self::fits($startMinute, $endMinute, $window[2] ?? null, $window[3] ?? null);
    }

    /**
     * True when at least one instance is inside the working hours, in the time zone of the booker.
     *
     * @param iterable<object> $instances reservation instances with StartDate() and EndDate()
     * @param array<int, array<int, string>> $hours
     */
    public static function anyInstancePermitted(iterable $instances, string $timezone, array $hours): bool
    {
        foreach ($instances as $instance) {
            $start = MriTime::fromDate($instance->StartDate(), $timezone);
            $end = MriTime::fromDate($instance->EndDate(), $timezone);
            if (self::permits($start, $end, $hours)) {
                return true;
            }
        }

        return false;
    }

    private static function fits(int $startMinute, int $endMinute, ?string $windowStart, ?string $windowEnd): bool
    {
        $from = self::parseConfigured($windowStart);
        $to = self::parseConfigured($windowEnd);
        if ($from === null || $to === null) {
            return false;
        }

        return $startMinute >= $from && $endMinute <= $to;
    }

    /**
     * Minutes since midnight; midnight counts as the end of the day, as the reservations start and end then.
     */
    private static function minuteOfDay(DateTimeInterface $moment): int
    {
        $minute = (int) $moment->format('H') * 60 + (int) $moment->format('i');

        return $minute === 0 ? self::MINUTES_PER_DAY : $minute;
    }

    /**
     * A configured "HH:MM" time in minutes. "00:00" stays at the start of the day; "24:00" is the end of it.
     */
    private static function parseConfigured(?string $time): ?int
    {
        if ($time === null || !preg_match('/^(\d{1,2}):(\d{2})$/', $time, $parts)) {
            return null;
        }

        return (int) $parts[1] * 60 + (int) $parts[2];
    }
}
