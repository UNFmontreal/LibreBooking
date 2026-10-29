<?php

declare(strict_types=1);

namespace LibreBooking\Mri\Cron;

use DateTimeImmutable;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\LibreBookingMailer;
use LibreBooking\Mri\MriReservation;
use LibreBooking\Mri\LibreBookingReservationSource;
use LibreBooking\Mri\ScreeningLinks;
use LibreBooking\Mri\ScreeningLookup;
use LibreBooking\Mri\Settings;

/**
 * Emails the owner of an upcoming reservation until its screening is completed.
 *
 * Phantom reservations need no screening. A later visit of a multi-session study (MultiX) is covered by the first visit.
 */
final class ScreeningReminderJob
{
    public function __construct(
        private readonly RemoteControl $remote,
        private readonly ScreeningLookup $lookup,
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
        $until = $now->modify('+' . $this->settings->lookAheadDays . ' days');
        $pending = $this->reservations->startingBetween($now, $until, $resourceIds, \ReservationStatus::Pending);
        if (empty($pending)) {
            return 0;
        }

        return $this->remote->withSession(function (string $sessionKey) use ($pending): int {
            $sent = 0;
            foreach ($pending as $reservation) {
                if ($this->remind($sessionKey, $reservation)) {
                    $sent++;
                }
            }

            return $sent;
        });
    }

    private function remind(string $sessionKey, MriReservation $reservation): bool
    {
        if ($reservation->isPhantom) {
            return false;
        }

        $state = $this->lookup->stateFor($sessionKey, $reservation);
        if ($state === null || $state->completed || $state->isMultiX) {
            return false;
        }

        $values = [
            '{FIRST}' => $reservation->ownerFirstName,
            '{LAST}' => $reservation->ownerLastName,
            '{DATE}' => $reservation->start->format($this->settings->dateFormat),
            '{START}' => $reservation->start->format($this->settings->timeFormat),
            '{END}' => $reservation->end->format($this->settings->timeFormat),
            '{SESSION}' => $reservation->sessionName,
            '{TOKEN}' => $reservation->referenceNumber,
            '{SCREENING_NOT_COMPLETED}' => $this->settings->screeningNotCompletedText,
            '{LINK}' => $this->surveyUrl($reservation->referenceNumber),
        ];

        $this->mailer->send(
            $reservation->ownerEmail,
            $reservation->ownerFirstName . $reservation->ownerLastName,
            strtr($this->settings->screeningEmailSubject, $values),
            strtr($this->settings->screeningEmailBody, $values),
        );

        return true;
    }

    private function surveyUrl(string $token): string
    {
        return ScreeningLinks::surveyTokenUrl($this->settings->lsBaseUrl, $this->settings->screeningSurveyId, $token);
    }
}
