<?php

use LibreBooking\Mri\MriMessages;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\LogbookQuestions;
use LibreBooking\Mri\MriReservationContext;
use LibreBooking\Mri\ParticipantHandover;

/**
 * Shared behaviour of the MRI post-reservation notifiers.
 *
 * A notifier only works on reservations that use a monitored resource and that carry the context built by the
 * MRI pre-reservation validation. The context holds the screening state and the LimeSurvey changes decided before the
 * reservation was saved; everything else is read from the reservation. The base notification of the decorated service
 * always runs.
 */
abstract class MRIPostResNotifier implements IReservationNotificationService
{
    /**
     * Custom attribute that records a validated phantom reservation.
     */

    /**
     * @var IReservationNotificationService
     */
    protected $base;

    public function __construct(IReservationNotificationService $base)
    {
        $this->base = $base;
    }

    /**
     * @param ReservationSeries|ExistingReservationSeries $reservationSeries
     * @return void
     */
    public function Notify($reservationSeries)
    {
        $context = $this->monitoredContext($reservationSeries);
        if ($context !== null) {
            $this->process($reservationSeries, $context);
        }

        $this->base->Notify($reservationSeries);
    }

    /**
     * Runs the notifier's LimeSurvey and database work for a monitored reservation.
     *
     * @param ReservationSeries|ExistingReservationSeries $reservationSeries
     */
    abstract protected function process($reservationSeries, MriReservationContext $context): void;

    /**
     * @return MriReservationContext|null null when the reservation is not monitored or has no context
     */
    private function monitoredContext($reservationSeries): ?MriReservationContext
    {
        if (!$this->usesMonitoredResource($reservationSeries)) {
            return null;
        }

        $context = MriReservationContext::for($reservationSeries);
        if ($context === null) {
            Log::Error('No MRI context attached to reservation series %s; skipping %s', $reservationSeries->SeriesId(), static::class);
        }

        return $context;
    }

    private function usesMonitoredResource($reservationSeries): bool
    {
        $monitored = MriPostResSettings::monitoredResourceNames();
        foreach ($reservationSeries->AllResources() as $resource) {
            if (in_array($resource->GetName(), $monitored, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Runs the work with a LimeSurvey session, which is released afterwards.
     *
     * @param callable(RemoteControl, string): void $work receives the remote control client and the session key
     */
    protected function withLimesurvey(callable $work): void
    {
        $remote = new RemoteControl(MriPostResSettings::lsBaseUrl(), MriPostResSettings::lsUser(), MriPostResSettings::lsPassword());
        $remote->withSession(function (string $sessionKey) use ($remote, $work): void {
            $work($remote, $sessionKey);
        });
    }

    /**
     * The answers of a logbook response, keyed by the response field of each question.
     *
     * @param object $lsClient LimeSurvey remote control client
     * @param array<string, mixed> $values answer by question name, one of the LogbookQuestions constants
     * @return array<string, mixed>
     */
    protected function logbookAnswers($lsClient, string $sessionKey, string $token, array $values): array
    {
        $surveyId = MriPostResSettings::logbookSurveyId();
        $questions = new LogbookQuestions(
            $surveyId,
            sys_get_temp_dir() . '/librebooking-logbook-questions-' . sha1(MriPostResSettings::lsBaseUrl() . $surveyId) . '.json'
        );

        return ['token' => $token] + $questions->answers($values, fn () => $lsClient->listQuestions($sessionKey, $surveyId));
    }

    /**
     * The session name of the reservation, as booked.
     */
    protected function sessionName($series): string
    {
        return trim((string) $series->GetAttributeValue(MriPostResSettings::sessionNameAttributeId()));
    }

    protected function researchAssociate($series): string
    {
        return trim((string) $series->GetAttributeValue(MriPostResSettings::raAttributeId()));
    }

    protected function isPhantom($series): bool
    {
        return (bool) $series->GetAttributeValue(MriPostResSettings::phantomAttributeId());
    }

    protected function hasTechnicalIssue($series): bool
    {
        return (bool) $series->GetAttributeValue(MriPostResSettings::unfTechnicalIssueAttributeId());
    }

    /**
     * The display name of the MRI operator of the reservation.
     */
    protected function operatorName($series): string
    {
        return trim((string) $series->GetAttributeValue(MriPostResSettings::operatorAttributeId()));
    }

    protected function operatorUserId($series): int
    {
        return MriPostResSettings::operatorUserId($this->operatorName($series));
    }

    /**
     * Registers a screening participant in LimeSurvey. A duplicate token is logged, not raised.
     *
     * @param RemoteControl $lsClient LimeSurvey remote control client
     */
    protected function registerParticipant($lsClient, string $sessionKey, int $surveyId, string $firstname, string $token, int $usesLeft): void
    {
        Log::Debug('Creating screening participant with uses_left: %s', $usesLeft);

        $participants = [['firstname' => $firstname, 'token' => $token, 'usesleft' => $usesLeft]];
        $response = $lsClient->addParticipants($sessionKey, $surveyId, $participants);

        if (MriLimesurvey::isDuplicateTokenError($response, $token)) {
            Log::Debug(MriMessages::text(MriMessages::DUPLICATED_TOKEN));
        }
    }

    /**
     * Makes sure the logbook survey has a participant for the token, so entries can be recorded against it.
     * A participant that exists already is left alone.
     *
     * @param object $lsClient LimeSurvey remote control client
     */
    protected function ensureLogbookParticipant($lsClient, string $sessionKey, string $token): void
    {
        $lsClient->addParticipants($sessionKey, MriPostResSettings::logbookSurveyId(), [['token' => $token]]);
    }

    /**
     * Looks up the first two complete screening responses of the participant, used as the logbook link ids.
     *
     * @param RemoteControl $lsClient LimeSurvey remote control client
     * @param bool $removeIncomplete delete the incomplete responses when the participant has more than two groups
     * @return int[] [first, second], 0 when a response does not exist
     */
    protected function screeningResponseIds($lsClient, string $sessionKey, string $participantName, int $surveyId, bool $removeIncomplete = false): array
    {
        $ids = [0, 0];

        $participant = $lsClient->participant($sessionKey, $surveyId, ['firstname' => $participantName]);
        if ($participant === null) {
            return $ids;
        }

        $responses = $lsClient->responseIds($sessionKey, $surveyId, $participant['token']);
        if (count($responses) === 1) {
            return [$responses[0], 0];
        }
        if (empty($responses)) {
            return $ids;
        }

        $complete = $lsClient->exportResponses($sessionKey, $surveyId, $participant['token'], 'complete');
        $ids = [RemoteControl::firstResponseId($complete[0] ?? []) ?? 0, RemoteControl::firstResponseId($complete[1] ?? []) ?? 0];

        if ($removeIncomplete && count($complete) > 2) {
            foreach ($lsClient->exportResponses($sessionKey, $surveyId, $participant['token'], 'incomplete') as $group) {
                $responseId = RemoteControl::firstResponseId($group);
                if ($responseId !== null) {
                    $lsClient->deleteResponse($sessionKey, $surveyId, $responseId);
                }
            }
        }

        return $ids;
    }

    /**
     * Hands the screening participant of the reservation over to the participant's next reservation.
     *
     * @param object $lsClient LimeSurvey remote control client
     */
    protected function applyHandover($lsClient, string $sessionKey, int $surveyId, string $reference, ParticipantHandover $handover): void
    {
        $query = ['token' => $reference];

        if ($handover->mode === ParticipantHandover::LASTNAME) {
            $lsClient->setParticipantProperties($sessionKey, $surveyId, $query, ['lastname' => $handover->nextToken]);
            return;
        }

        $tokenData = ['token' => $handover->nextToken];
        if ($handover->mode === ParticipantHandover::TOKEN_AND_RESPONSE) {
            // a response cannot be moved by its participant: it carries its own copy of the token
            $responseIds = $lsClient->responseIds($sessionKey, $surveyId, $reference);
            if (!empty($responseIds)) {
                $lsClient->updateResponse($sessionKey, $surveyId, ['id' => $responseIds[0], 'token' => $handover->nextToken]);
            }
        }
        $lsClient->setParticipantProperties($sessionKey, $surveyId, $query, $tokenData);
    }

    /**
     * Builds the placeholder values of the logbook link for one reservation instance.
     *
     * @param ReservationSeries|ExistingReservationSeries $series
     * @param Reservation $instance
     * @param int[] $responseIds screening response ids, [0] and [1]
     */
    protected function logbookLink($series, $instance, array $responseIds): string
    {
        $timezone = $series->BookedBy()->Timezone;
        $values = [
            '[LS_BASEURL]' => MriPostResSettings::lsBaseUrl(),
            '[LogbookSurveyID]' => (string) MriPostResSettings::logbookSurveyId(),
            '[TOKEN]' => $instance->ReferenceNumber(),
            '[session_name]' => urlencode($this->sessionName($series)),
            '[RA]' => urlencode($this->researchAssociate($series)),
            '[START_TIME]' => $instance->StartDate()->ToTimezone($timezone)->Format('H:i'),
            '[END_TIME]' => $instance->EndDate()->ToTimezone($timezone)->Format('H:i'),
            '[DATE]' => $instance->StartDate()->ToTimezone($timezone)->Format('Y-m-d'),
            '[URL_ID]' => (string) $responseIds[0],
            '[URL_ID2]' => (string) $responseIds[1],
        ];

        return strtr(MriPostResSettings::text(LimesurveyScreeningPreConfigKeys::LINK_TO_LOGBOOK), $values);
    }
}
