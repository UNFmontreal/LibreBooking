<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Reservation source backed by LibreBooking's repositories.
 */
class LibreBookingReservationSource
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function startingBetween(DateTimeImmutable $from, DateTimeImmutable $to, array $resourceIds, int $statusId): array
    {
        if (empty($resourceIds)) {
            return [];
        }

        $items = (new \ReservationViewRepository())->GetReservations(
            MriTime::toDate($from),
            MriTime::toDate($to),
            \ReservationViewRepository::ALL_USERS,
            \ReservationUserLevel::OWNER,
            \ReservationViewRepository::ALL_SCHEDULES,
            $resourceIds,
        );

        $reservations = [];
        $seen = [];
        foreach ($items as $item) {
            $reference = $item->GetReferenceNumber();
            if (isset($seen[$reference]) || $this->statusOf($item->IsPending()) !== $statusId) {
                continue;
            }
            $seen[$reference] = true;

            $reservation = $this->load($item);
            if ($reservation !== null) {
                $reservations[] = $reservation;
            }
        }

        return $reservations;
    }

    public function resourceIdsByName(array $resourceNames): array
    {
        return ResourceNames::idsOf($resourceNames);
    }

    /**
     * Status change written directly: the series repository only persists through its save path.
     */
    public function setStatus(int $seriesId, int $statusId): void
    {
        \ServiceLocator::GetDatabase()->Query(
            new \AdHocCommand("update reservation_series set status_id = $statusId where series_id = $seriesId")
        );
    }

    private function load(\ReservationItemView $item): ?MriReservation
    {
        $referenceNumber = $item->GetReferenceNumber();
        $series = (new \ReservationRepository())->LoadByReferenceNumber($referenceNumber);
        if ($series === null) {
            return null;
        }

        $owner = (new \UserRepository())->LoadById($series->UserId());
        if ($owner === null) {
            return null;
        }

        $timezone = new DateTimeZone((string) $owner->Timezone());

        return new MriReservation(
            seriesId: (int) $series->SeriesId(),
            referenceNumber: $referenceNumber,
            sessionName: (string) $series->GetAttributeValue($this->settings->sessionNameAttributeId),
            ra: (string) $series->GetAttributeValue($this->settings->raAttributeId),
            isPhantom: (bool) $series->GetAttributeValue($this->settings->phantomAttributeId),
            ownerEmail: (string) $owner->EmailAddress(),
            ownerFirstName: (string) $owner->FirstName(),
            ownerLastName: (string) $owner->LastName(),
            start: $this->inTimezone($item->GetStartDate()->Timestamp(), $timezone),
            end: $this->inTimezone($item->GetEndDate()->Timestamp(), $timezone),
            statusId: (int) $series->StatusId(),
            logbookLink: (string) $series->GetAttributeValue($this->settings->logbookLinkAttributeId),
        );
    }

    private function inTimezone(int $timestamp, DateTimeZone $timezone): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
    }

    private function statusOf(bool $isPending): int
    {
        return $isPending ? \ReservationStatus::Pending : \ReservationStatus::Created;
    }
}
