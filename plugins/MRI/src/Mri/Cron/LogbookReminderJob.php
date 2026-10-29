<?php

declare(strict_types=1);

namespace LibreBooking\Mri\Cron;

use DateTimeImmutable;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\LibreBookingMailer;
use LibreBooking\Mri\MriReservation;
use LibreBooking\Mri\LibreBookingReservationSource;
use LibreBooking\Mri\Settings;

/**
 * Reminds the MRI operator to complete the logbook entry of a reservation that has finished.
 *
 * Reservations that finished between one and three days ago are checked. A logbook entry that is still incomplete is
 * reported to the operator. A completed one is tidied when the participant has an extra incomplete response.
 */
final class LogbookReminderJob
{
    private const LOOK_BACK_FROM_DAYS = 3;
    private const LOOK_BACK_TO_DAYS = 1;

    public function __construct(
        private readonly RemoteControl $remote,
        private readonly LibreBookingReservationSource $reservations,
        private readonly LibreBookingMailer $mailer,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @return int number of reminders sent
     */
    public function run(DateTimeImmutable $now): int
    {
        $resourceIds = $this->reservations->resourceIdsByName($this->settings->monitoredResourceNames);
        $finished = $this->reservations->startingBetween(
            $now->modify('-' . self::LOOK_BACK_FROM_DAYS . ' days'),
            $now->modify('-' . self::LOOK_BACK_TO_DAYS . ' days'),
            $resourceIds,
            \ReservationStatus::Created,
        );
        if (empty($finished)) {
            return 0;
        }

        return $this->remote->withSession(function (string $sessionKey) use ($finished): int {
            $reminders = 0;
            foreach ($finished as $reservation) {
                if ($this->review($sessionKey, $reservation)) {
                    $reminders++;
                }
            }

            return $reminders;
        });
    }

    private function review(string $sessionKey, MriReservation $reservation): bool
    {
        $participant = $this->remote->participant($sessionKey, $this->settings->logbookSurveyId, ['token' => $reservation->referenceNumber]);

        if (($participant['completed'] ?? null) === 'N') {
            $this->remind($reservation);
            return true;
        }

        $this->removeIncompleteResponses($sessionKey, $reservation->referenceNumber);
        return false;
    }

    private function remind(MriReservation $reservation): void
    {
        $values = [
            '{DATE}' => $reservation->start->format($this->settings->dateFormat),
            '{START}' => $reservation->start->format($this->settings->timeFormat),
            '{END}' => $reservation->end->format($this->settings->timeFormat),
            '{SESSION}' => $reservation->sessionName,
            '{TOKEN}' => $reservation->referenceNumber,
            '{LINK}' => $reservation->logbookLink,
        ];

        $this->mailer->send(
            $this->settings->logbookOperatorAddress,
            $this->settings->supportName,
            strtr($this->settings->logbookEmailSubject, $values),
            strtr($this->settings->logbookEmailBody, $values),
        );
    }

    /**
     * Only applies when the logbook has a single complete response and extra incomplete ones.
     */
    private function removeIncompleteResponses(string $sessionKey, string $token): void
    {
        $surveyId = $this->settings->logbookSurveyId;
        if (count($this->remote->responseIds($sessionKey, $surveyId, $token)) === 1) {
            return;
        }

        $complete = $this->remote->exportResponses($sessionKey, $surveyId, $token, 'complete');
        if (count($complete[0] ?? []) !== 1) {
            return;
        }

        foreach ($this->remote->exportResponses($sessionKey, $surveyId, $token, 'incomplete') as $group) {
            $responseId = RemoteControl::firstResponseId($group);
            if ($responseId !== null) {
                $this->remote->deleteResponse($sessionKey, $surveyId, $responseId);
            }
        }
    }
}
