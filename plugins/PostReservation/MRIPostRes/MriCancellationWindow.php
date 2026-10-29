<?php

/**
 * Decides whether a reservation starts inside the late-cancellation window.
 */
final class MriCancellationWindow
{
    /**
     * True when $start is before midnight of the day that is $days days after $now.
     * Such a reservation counts as a late cancellation: the support team is notified.
     */
    public static function startsWithin(DateTimeInterface $start, int $days, DateTimeInterface $now): bool
    {
        $midnight = new DateTimeImmutable($now->format('Y-m-d') . ' 00:00:00', $now->getTimezone());
        $limit = $midnight->modify('+' . $days . ' days');

        return $start < $limit;
    }
}
