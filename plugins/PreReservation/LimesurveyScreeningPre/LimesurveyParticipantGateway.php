<?php

use LibreBooking\Limesurvey\Participant;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\ParticipantDirectory;

/**
 * Looks up screening participants in the screening survey through the LimeSurvey remote control.
 */
class LimesurveyParticipantGateway implements ParticipantDirectory
{
    private RemoteControl $remote;
    private int $surveyId;

    public function __construct(string $baseUrl, string $user, string $password, int $surveyId)
    {
        $this->remote = new RemoteControl($baseUrl, $user, $password);
        $this->surveyId = $surveyId;
    }

    public function participant(array $query): ?Participant
    {
        return $this->remote->withSession(
            fn (string $sessionKey): ?Participant => Participant::fromProperties(
                $this->remote->participant($sessionKey, $this->surveyId, $query)
            )
        );
    }

    /**
     * @throws RuntimeException when LimeSurvey cannot be reached or rejects the request
     */
    public function participantExists(string $firstname): bool
    {
        return $this->participant(['firstname' => $firstname]) !== null;
    }
}
