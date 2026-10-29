<?php

/**
 * Regular hours parsed from a string such as "Mon-Fri:08:00-18:00;Sat:09:00-12:00".
 *
 * Each entry lists weekdays (a single day, a range like Mon-Fri, or a comma separated
 * combination) followed by a start and end time in the schedule's timezone.
 * An end time of 24:00 means the end of the day.
 */
class OffHoursSchedule
{
    private const MINUTES_PER_HOUR = 60;
    private const HOURS_PER_DAY = 24;
    private const MINUTES_PER_DAY = 1440;
    private const DAYS_PER_WEEK = 7;
    private const ENTRY_PATTERN = '/^\s*([A-Za-z,\s-]+?)\s*:\s*(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})\s*$/';

    // PHP weekday numbers (0 = Sunday), keyed by the first three letters of the day name
    private const WEEKDAYS = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];

    /**
     * @var array<int, array{day: int, start: int, end: int}> minutes from midnight
     */
    private array $windows = [];

    public function __construct(string $regularHours)
    {
        foreach (explode(';', $regularHours) as $entry) {
            if (trim($entry) === '') {
                continue;
            }

            $windows = $this->ParseEntry($entry);
            if ($windows === null) {
                Log::Error('Ignoring invalid OffHours regular hours entry: %s. Entries are separated by ";", for example Mon-Fri:08:00-12:00;Mon-Fri:13:00-17:00', $entry);
                continue;
            }

            $this->windows = array_merge($this->windows, $windows);
        }
    }

    /**
     * Whether the whole interval falls within a single regular window on its start day.
     * An interval ending exactly at midnight of the following day is treated as ending on the start day.
     *
     * @param Date $start
     * @param Date $end
     * @param string $timezone timezone of the schedule the regular hours belong to
     * @return bool
     */
    public function Contains(Date $start, Date $end, string $timezone): bool
    {
        $localStart = $start->ToTimezone($timezone);
        $localEnd = $end->ToTimezone($timezone);

        $startDay = $localStart->Format('Y-m-d');
        $startMinutes = $localStart->Hour() * self::MINUTES_PER_HOUR + $localStart->Minute();
        $endMinutes = $localEnd->Hour() * self::MINUTES_PER_HOUR + $localEnd->Minute();

        if ($localEnd->Format('Y-m-d') !== $startDay) {
            $endsAtNextMidnight = $endMinutes === 0 && $localEnd->Format('Y-m-d') === $localStart->AddDays(1)->Format('Y-m-d');
            if (!$endsAtNextMidnight) {
                return false;
            }
            $endMinutes = self::MINUTES_PER_DAY;
        }

        $weekday = (int) $localStart->Weekday();
        foreach ($this->windows as $window) {
            if ($window['day'] === $weekday && $window['start'] <= $startMinutes && $endMinutes <= $window['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $entry
     * @return array<int, array{day: int, start: int, end: int}>|null null when the entry is invalid
     */
    private function ParseEntry(string $entry): ?array
    {
        if (!preg_match(self::ENTRY_PATTERN, $entry, $matches)) {
            return null;
        }

        $days = $this->ParseDays($matches[1]);
        $start = $this->ParseTime($matches[2]);
        $end = $this->ParseTime($matches[3]);

        if ($days === null || $start === null || $end === null || $start >= $end) {
            return null;
        }

        $windows = [];
        foreach ($days as $day) {
            $windows[] = ['day' => $day, 'start' => $start, 'end' => $end];
        }

        return $windows;
    }

    /**
     * @param string $days e.g. "Mon-Fri" or "Mon,Wed,Sat"
     * @return int[]|null weekday numbers, null when a day name is invalid
     */
    private function ParseDays(string $days): ?array
    {
        $weekdays = [];
        foreach (explode(',', $days) as $part) {
            $bounds = explode('-', $part);
            if (count($bounds) > 2) {
                return null;
            }

            $first = self::WeekdayNumber($bounds[0]);
            $last = self::WeekdayNumber($bounds[count($bounds) - 1]);
            if ($first === null || $last === null) {
                return null;
            }

            // Walk forward from the first day to the last one, wrapping around the end of the week
            $day = $first;
            do {
                $weekdays[] = $day;
                $day = ($day + 1) % self::DAYS_PER_WEEK;
            } while ($day !== ($last + 1) % self::DAYS_PER_WEEK);
        }

        return array_values(array_unique($weekdays));
    }

    /**
     * @param string $time HH:MM, where 24:00 is allowed
     * @return int|null minutes from midnight, null when the time is invalid
     */
    private function ParseTime(string $time): ?int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        if ($minutes >= self::MINUTES_PER_HOUR || $hours > self::HOURS_PER_DAY || ($hours === self::HOURS_PER_DAY && $minutes > 0)) {
            return null;
        }

        return $hours * self::MINUTES_PER_HOUR + $minutes;
    }

    private static function WeekdayNumber(string $name): ?int
    {
        return self::WEEKDAYS[strtolower(substr(trim($name), 0, 3))] ?? null;
    }
}
