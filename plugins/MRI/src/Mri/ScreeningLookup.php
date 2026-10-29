<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use LibreBooking\Limesurvey\Participant;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Studies\StudyCatalog;

/**
 * Works out the screening state of a reservation from LimeSurvey.
 */
final class ScreeningLookup
{
    public function __construct(
        private readonly RemoteControl $remote,
        private readonly int $screeningSurveyId,
        private readonly StudyCatalog $studies,
    ) {
    }

    /**
     * @return ScreeningState|null null when the session name matches no study
     */
    public function stateFor(string $sessionKey, MriReservation $reservation): ?ScreeningState
    {
        $match = $this->studies->find($reservation->sessionName);
        if ($match === null) {
            return null;
        }

        $byToken = Participant::fromProperties(
            $this->remote->participant($sessionKey, $this->screeningSurveyId, ['token' => $reservation->referenceNumber])
        );

        $participantName = ParticipantName::resolve($match, $reservation->sessionName);
        $byName = null;
        if ($match->study->multisession && $byToken === null) {
            $byName = Participant::fromProperties(
                $this->remote->participant($sessionKey, $this->screeningSurveyId, ['firstname' => $participantName])
            );
        }

        return ScreeningState::derive(
            $match,
            $reservation->sessionName,
            $reservation->referenceNumber,
            $reservation->isPhantom,
            $byToken,
            $byName,
        );
    }
}
