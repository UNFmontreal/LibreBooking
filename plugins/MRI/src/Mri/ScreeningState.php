<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use LibreBooking\Limesurvey\Participant;
use LibreBooking\Studies\StudyMatch;

/**
 * Whether a reservation's screening is done, and which LimeSurvey participant carries it.
 *
 * Single-session reservations are screened under their own reference number. A multi-session reservation with its
 * own participant is the first visit (Multi1). A multi-session reservation without one is a later visit (MultiX) and
 * uses the participant of its study.
 */
final readonly class ScreeningState
{
    public function __construct(
        public ?string $participantToken,
        public bool $completed,
        public bool $isMulti,
        public bool $isMulti1,
        public bool $isMultiX,
        public string $participantName,
        public ?string $ethic,
    ) {
    }

    /**
     * @param Participant|null $byToken the participant found by the reservation's reference number
     * @param Participant|null $byName the participant found by the study's participant name (multi-session only)
     */
    public static function derive(
        StudyMatch $match,
        string $sessionName,
        string $referenceNumber,
        bool $isPhantom,
        ?Participant $byToken,
        ?Participant $byName,
    ): self {
        $isMulti = $match->study->multisession;
        $participantName = ParticipantName::resolve($match, $sessionName);
        $ethic = $match->study->ethic;

        if (($byToken !== null && $isMulti) || (!$isMulti && !$isPhantom)) {
            return new self($referenceNumber, $byToken?->completed ?? false, $isMulti, $isMulti, false, $participantName, $ethic);
        }

        if ($isMulti) {
            return new self($byName?->token, $byName?->completed ?? false, true, false, true, $participantName, $ethic);
        }

        // a single-session phantom reservation has no screening participant
        return new self(null, false, false, false, false, $participantName, $ethic);
    }
}
