<?php

use LibreBooking\Mri\MriTime;
use LibreBooking\Mri\ReservationFacts;
use LibreBooking\Studies\StudyMatch;

/**
 * Reads the screening facts of a reservation series from LibreBooking.
 */
final class LimesurveyReservationFacts
{
    /**
     * @param ReservationSeries|ExistingReservationSeries|object $series
     */
    public static function fromSeries($series, LimesurveyScreeningSettings $settings, ?StudyMatch $study, string $sessionName): ReservationFacts
    {
        $instance = $series->CurrentInstance();

        return new ReservationFacts(
            (string) $instance->ReferenceNumber(),
            $sessionName,
            (bool) $series->GetAttributeValue($settings->phantomAttributeId),
            $study,
            array_map('intval', $series->AllResourceIds()),
            MriTime::fromDate($instance->StartDate()),
            MriTime::fromDate($instance->EndDate()),
        );
    }
}
