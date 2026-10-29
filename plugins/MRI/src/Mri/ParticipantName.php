<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use LibreBooking\Studies\StudyMatch;

/**
 * The screening participant name of a session.
 */
final class ParticipantName
{
    /**
     * Multi-session studies share one participant across their bookings: the study and subject named by the groups of
     * the study regex, for example HOF_001 for HOF_001_pre1. Any other session is its own participant, so its name is
     * the participant name.
     */
    public static function resolve(StudyMatch $match, string $sessionName): string
    {
        return $match->participantKey() ?? $sessionName;
    }
}
