<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use DateTimeInterface;

/**
 * Finds the next reservation of a multi-session participant, after a given reservation has ended.
 *
 * The participant of each later reservation on the same resource is read from its session name with the study regexes.
 */
final class NextMultiSession
{
    /**
     * @param int[] $resourceIds
     * @param callable(string): ?string $participantOf the participant name of a session name, null when no study matches
     * @return string|null reference number of the next reservation of the participant
     */
    public static function referenceAfter(string $participantName, DateTimeInterface $after, int $sessionAttributeId, array $resourceIds, callable $participantOf): ?string
    {
        return self::pick($participantName, $after, self::candidates($after, $sessionAttributeId, $resourceIds), $participantOf);
    }

    /**
     * @param iterable<array{0: DateTimeInterface, 1: string, 2: string}> $candidates [start, reference number, session name]
     * @param callable(string): ?string $participantOf
     */
    public static function pick(string $participantName, DateTimeInterface $after, iterable $candidates, callable $participantOf): ?string
    {
        if ($participantName === '') {
            return null;
        }

        foreach ($candidates as [$start, $reference, $sessionName]) {
            if ($start->getTimestamp() <= $after->getTimestamp()) {
                continue;
            }
            if ($participantOf($sessionName) === $participantName) {
                return $reference;
            }
        }

        return null;
    }

    /**
     * @param int[] $resourceIds
     * @return iterable<array{0: DateTimeInterface, 1: string, 2: string}>
     */
    private static function candidates(DateTimeInterface $after, int $sessionAttributeId, array $resourceIds): iterable
    {
        $views = (new \ReservationViewRepository())->GetReservations(
            MriTime::toDate($after),
            \Date::Max(),
            \ReservationViewRepository::ALL_USERS,
            \ReservationUserLevel::OWNER,
            \ReservationViewRepository::ALL_SCHEDULES,
            $resourceIds,
        );

        $repository = new \ReservationRepository();
        foreach ($views as $view) {
            $series = $repository->LoadByReferenceNumber($view->GetReferenceNumber());
            if ($series === null) {
                continue;
            }

            yield [
                MriTime::fromDate($view->GetStartDate()),
                (string) $view->GetReferenceNumber(),
                (string) $series->GetAttributeValue($sessionAttributeId),
            ];
        }
    }
}
