<?php

/**
 * Updates the status of a reservation series after it has been saved.
 */
final class MriSeriesStatus
{
    /**
     * Writes the status column directly. The series repository only persists a series through its save path, and this
     * update runs from a notification after that save, so there is no LibreBooking command for it yet.
     * Both arguments are integers, so the statement cannot be injected.
     */
    public static function set(int $seriesId, int $statusId): void
    {
        ServiceLocator::GetDatabase()->Query(
            new AdHocCommand("update reservation_series set status_id = $statusId where series_id = $seriesId")
        );
    }
}
