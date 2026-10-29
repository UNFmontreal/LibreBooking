<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * A logbook entry for a reservation that was moved or shortened shortly before it starts.
 *
 * The entry records the reservation as it was before the update.
 */
final readonly class LogbookUpdate
{
    private const FORMAT = 'Y-m-d H:i';
    private const NOTICE_PERIOD = 'P7D';

    private function __construct(
        public string $endTime,
        public string $commentSuffix,
    ) {
    }

    /**
     * @return self|null null when the update is not logged: the reservation started more than seven days ahead
     *         before the update, or it moved earlier without being shortened, or it was extended in place
     */
    public static function detect(
        DateTimeInterface $previousStart,
        DateTimeInterface $previousEnd,
        DateTimeInterface $currentStart,
        DateTimeInterface $currentEnd,
        DateTimeInterface $now,
    ): ?self {
        $limit = DateTimeImmutable::createFromInterface($now)->add(new DateInterval(self::NOTICE_PERIOD));
        if ($previousStart->getTimestamp() >= $limit->getTimestamp()) {
            return null;
        }

        $previousMinutes = intdiv($previousEnd->getTimestamp() - $previousStart->getTimestamp(), 60);
        $currentMinutes = intdiv($currentEnd->getTimestamp() - $currentStart->getTimestamp(), 60);
        $lessTime = $currentMinutes < $previousMinutes;
        $movedEarlier = $currentStart->getTimestamp() < $previousStart->getTimestamp();
        $moved = $currentStart->getTimestamp() !== $previousStart->getTimestamp();

        if ($lessTime && $movedEarlier) {
            return self::shortened('Reservation updated to an earlier date.', $previousStart, $previousEnd, $currentStart, $currentEnd, $previousMinutes - $currentMinutes);
        }
        if (!$movedEarlier && $moved) {
            // moved to a later date: the entry covers the whole previous reservation
            return new self($previousEnd->format(self::FORMAT), '');
        }
        if (!$moved && $lessTime) {
            return self::shortened('Reservation updated with less time.', $previousStart, $previousEnd, $currentStart, $currentEnd, $previousMinutes - $currentMinutes);
        }

        return null;
    }

    private static function shortened(
        string $reason,
        DateTimeInterface $previousStart,
        DateTimeInterface $previousEnd,
        DateTimeInterface $currentStart,
        DateTimeInterface $currentEnd,
        int $cutMinutes,
    ): self {
        // the entry covers the time that was cut, counted from the previous start
        $end = DateTimeImmutable::createFromInterface($previousStart)->add(new DateInterval('PT' . $cutMinutes . 'M'));

        return new self(
            $end->format(self::FORMAT),
            ' ' . $reason
            . ' Before: ' . $previousStart->format(self::FORMAT) . ' to ' . $previousEnd->format(self::FORMAT)
            . ' After: ' . $currentStart->format(self::FORMAT) . ' to ' . $currentEnd->format(self::FORMAT)
        );
    }
}
