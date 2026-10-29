<?php

declare(strict_types=1);

namespace LibreBooking\Mri\Cron;

use DateTimeImmutable;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\LibreBookingAttributeStore;
use LibreBooking\Mri\MriReservation;
use LibreBooking\Mri\LibreBookingReservationSource;
use LibreBooking\Mri\ScreeningLookup;
use LibreBooking\Mri\ScreeningState;
use LibreBooking\Mri\Settings;

/**
 * Marks pending reservations as Created once their screening is completed in LimeSurvey.
 *
 * For each completed screening it: records the participant in the logbook survey, sets the reservation status,
 * and writes the logbook link. Responses that are not complete are removed when the participant has several.
 */
final class ValidScreeningJob
{
    public function __construct(
        private readonly RemoteControl $remote,
        private readonly ScreeningLookup $lookup,
        private readonly LibreBookingReservationSource $reservations,
        private readonly LibreBookingAttributeStore $attributes,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return int number of reservations whose screening was completed
     */
    public function run(DateTimeImmutable $now): int
    {
        $resourceIds = $this->reservations->resourceIdsByName($this->settings->monitoredResourceNames);
        $until = $now->modify('+' . $this->settings->lookAheadDays . ' days');
        $pending = $this->reservations->startingBetween($now, $until, $resourceIds, \ReservationStatus::Pending);
        if (empty($pending)) {
            return 0;
        }

        return $this->remote->withSession(function (string $sessionKey) use ($pending): int {
            $completed = 0;
            foreach ($pending as $reservation) {
                if ($this->screen($sessionKey, $reservation)) {
                    $completed++;
                }
            }

            return $completed;
        });
    }

    private function screen(string $sessionKey, MriReservation $reservation): bool
    {
        $state = $this->lookup->stateFor($sessionKey, $reservation);
        if ($state === null) {
            \Log::Error('MRI valid screening: no study matches session %s', $reservation->sessionName);
            return false;
        }
        if (!$state->completed || $state->participantToken === null) {
            return false;
        }

        $this->attributes->replaceIfPresent(
            $reservation->seriesId,
            $this->settings->screeningLinkAttributeId,
            $this->settings->screeningLinkText . ' ' . $this->settings->completedText
        );

        $responseIds = $reservation->isPhantom ? [0, 0] : $this->responseIds($sessionKey, $state->participantToken);

        $this->remote->addParticipants($sessionKey, $this->settings->logbookSurveyId, [['token' => $state->participantToken]]);
        $this->reservations->setStatus($reservation->seriesId, \ReservationStatus::Created);
        $this->attributes->set(
            $reservation->seriesId,
            $this->settings->logbookLinkAttributeId,
            $this->logbookUrl($reservation, $state, $responseIds)
        );

        return true;
    }

    /**
     * The ids of the first two complete responses. Incomplete responses are deleted when there are more than two groups.
     *
     * @return int[] [first, second], 0 when a response does not exist
     */
    private function responseIds(string $sessionKey, string $token): array
    {
        $ids = [0, 0];
        $surveyId = $this->settings->screeningSurveyId;

        $existing = $this->remote->responseIds($sessionKey, $surveyId, $token);
        if (empty($existing) || empty($existing[0])) {
            return $ids;
        }

        $groups = $this->remote->exportResponses($sessionKey, $surveyId, $token, 'complete');
        $ids[0] = RemoteControl::firstResponseId($groups[0] ?? []) ?? 0;
        $ids[1] = RemoteControl::firstResponseId($groups[1] ?? []) ?? 0;

        if (count($groups) > 2) {
            foreach ($this->remote->exportResponses($sessionKey, $surveyId, $token, 'incomplete') as $group) {
                $responseId = RemoteControl::firstResponseId($group);
                if ($responseId !== null) {
                    $this->remote->deleteResponse($sessionKey, $surveyId, $responseId);
                }
            }
        }

        return $ids;
    }

    /**
     * @param int[] $responseIds
     */
    private function logbookUrl(MriReservation $reservation, ScreeningState $state, array $responseIds): string
    {
        return strtr($this->settings->linkToLogbook, [
            '[LS_BASEURL]' => $this->settings->lsBaseUrl,
            '[LogbookSurveyID]' => (string) $this->settings->logbookSurveyId,
            '[TOKEN]' => (string) $state->participantToken,
            '[session_name]' => urlencode($reservation->sessionName),
            '[RA]' => urlencode($reservation->ra),
            '[START_TIME]' => $reservation->start->format('H:i'),
            '[END_TIME]' => $reservation->end->format('H:i'),
            '[DATE]' => $reservation->start->format('Y-m-d'),
            '[ETHIC]' => urlencode((string) $state->ethic),
            '[URL_ID]' => (string) $responseIds[0],
            '[URL_ID2]' => (string) $responseIds[1],
        ]);
    }
}
