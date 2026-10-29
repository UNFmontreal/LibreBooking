<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Fakes;

use DateTimeImmutable;
use LibreBooking\Mri\MriReservation;
use LibreBooking\Mri\LibreBookingReservationSource;

/**
 * Returns the reservations it was given for each status.
 */
class FixedReservationSource extends LibreBookingReservationSource
{
    /**
     * @var array<int, list<MriReservation>> status id => reservations
     */
    public array $byStatus = [];

    /**
     * @var array<int, int> seriesId => status id written
     */
    public array $statusWrites = [];

    /**
     * @param MriReservation[] $reservations
     */
    public function __construct(array $reservations)
    {
        parent::__construct(TestSupport::settings());
        $this->byStatus[\ReservationStatus::Pending] = [];
        $this->byStatus[\ReservationStatus::Created] = [];
        foreach ($reservations as $reservation) {
            $this->byStatus[$reservation->statusId][] = $reservation;
        }
    }

    public function startingBetween(DateTimeImmutable $from, DateTimeImmutable $to, array $resourceIds, int $statusId): array
    {
        return $this->byStatus[$statusId] ?? [];
    }

    public function resourceIdsByName(array $resourceNames): array
    {
        return [1];
    }

    public function setStatus(int $seriesId, int $statusId): void
    {
        $this->statusWrites[$seriesId] = $statusId;
    }
}
