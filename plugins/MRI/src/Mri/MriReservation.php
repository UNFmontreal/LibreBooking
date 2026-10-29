<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use DateTimeImmutable;

/**
 * A reservation as the MRI cron jobs see it. Times are in the owner's time zone.
 */
final readonly class MriReservation
{
    public function __construct(
        public int $seriesId,
        public string $referenceNumber,
        public string $sessionName,
        public string $ra,
        public bool $isPhantom,
        public string $ownerEmail,
        public string $ownerFirstName,
        public string $ownerLastName,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public int $statusId,
        public string $logbookLink,
    ) {
    }
}
