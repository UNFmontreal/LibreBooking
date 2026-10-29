<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use LibreBooking\Limesurvey\Participant;

/**
 * Looks up screening participants in LimeSurvey.
 */
interface ParticipantDirectory
{
    /**
     * @param array<string, mixed> $query for example ['token' => 'REF-1'] or ['firstname' => 'ANT_001']
     * @return Participant|null null when no participant matches the query
     * @throws \RuntimeException when LimeSurvey cannot be reached or rejects the request
     */
    public function participant(array $query): ?Participant;
}
