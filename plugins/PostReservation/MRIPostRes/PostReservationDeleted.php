<?php

use LibreBooking\Mri\MriMessages;
use LibreBooking\Mri\LibreBookingMailer;
use LibreBooking\Mri\LogbookQuestions;
use LibreBooking\Mri\MriReservationContext;
use LibreBooking\Mri\MriTime;

/**
 * Post-reservation work for a deleted reservation.
 *
 * Removes the screening responses and participant, clears the session name, notifies support when the cancellation
 * is late, and records the cancellation in the logbook.
 */
class PostReservationDeleted extends MRIPostResNotifier
{
    /**
     * Logbook entry text for a cancellation that is not late.
     */
    private const CANCEL_CONDITION_LATE = 'lessthan7days';
    private const CANCEL_CONDITION_ON_TIME = 'morethan7days';

    protected function process($reservationSeries, MriReservationContext $context): void
    {
        $this->withLimesurvey(function ($lsClient, string $sessionKey) use ($reservationSeries, $context): void {
            $surveyId = MriPostResSettings::screeningSurveyId();
            $seriesId = $reservationSeries->SeriesId();

            $currentStart = MriTime::fromDate($reservationSeries->CurrentInstance()->startDate());
            $isLate = MriCancellationWindow::startsWithin($currentStart, MriPostResSettings::maxDelayDaysToCancel(), new DateTime('NOW'));

            foreach ($reservationSeries->Instances() as $instance) {
                if (!$this->isPhantom($reservationSeries)) {
                    $reference = $reservationSeries->CurrentInstance()->ReferenceNumber();
                    if ($context->handover !== null) {
                        $this->applyHandover($lsClient, $sessionKey, $surveyId, $reference, $context->handover);
                    }
                    if ($context->deleteResponses) {
                        foreach ($lsClient->responseIds($sessionKey, $surveyId, $reference) as $responseId) {
                            $lsClient->deleteResponse($sessionKey, $surveyId, $responseId);
                        }
                    }
                    if ($context->deleteParticipantId !== null) {
                        $lsClient->deleteParticipants($sessionKey, $surveyId, [$context->deleteParticipantId]);
                    }
                }

                MriAttributeValues::remove($seriesId, MriPostResSettings::sessionNameAttributeId());

                if ($isLate) {
                    $this->notifySupport($reservationSeries, $instance);
                }

                $this->logCancellation($lsClient, $sessionKey, $reservationSeries, $isLate);
            }
        });
    }

    /**
     * Emails the support team about a cancellation inside the late-cancellation window.
     */
    private function notifySupport($reservationSeries, $instance): void
    {
        $values = [
            '{NAME}' => (string) $reservationSeries->BookedBy()->FullName(),
            '{EMAIL}' => $reservationSeries->BookedBy()->Email,
            '{DATE}' => date(MriPostResSettings::text(LimesurveyScreeningPreConfigKeys::EMAIL_DATE_FORMAT), $instance->StartDate()->Timestamp()),
            '{START}' => date(MriPostResSettings::text(LimesurveyScreeningPreConfigKeys::EMAIL_TIME_FORMAT), $instance->StartDate()->Timestamp()),
            '{END}' => date(MriPostResSettings::text(LimesurveyScreeningPreConfigKeys::EMAIL_TIME_FORMAT), $instance->EndDate()->Timestamp()),
            '{SESSION}' => $this->sessionName($reservationSeries),
            '{TOKEN}' => $reservationSeries->CurrentInstance()->ReferenceNumber(),
        ];

        $subject = strtr(MriMessages::text(MriMessages::EMAIL_DELETE_SUBJECT), $values);
        $body = strtr(MriMessages::text(MriMessages::EMAIL_DELETE_BODY), $values);

        (new LibreBookingMailer())->send(
            MriPostResSettings::text(LimesurveyScreeningPreConfigKeys::SUPPORT_EMAIL),
            MriPostResSettings::text(LimesurveyScreeningPreConfigKeys::SUPPORT_EMAIL_NAME),
            $subject,
            $body
        );
    }

    /**
     * Adds the cancellation entry to the logbook survey.
     */
    private function logCancellation($lsClient, string $sessionKey, $reservationSeries, bool $isLate): void
    {
        $current = $reservationSeries->CurrentInstance();
        $start = MriTime::fromDate($current->startDate());
        $end = MriTime::fromDate($current->endDate());
        $startDate = (clone $start)->setTime(0, 0, 0);
        $now = new DateTime('NOW');
        $headsUp = $now->diff($start)->format('%R%a');
        $condition = $isLate ? self::CANCEL_CONDITION_LATE : self::CANCEL_CONDITION_ON_TIME;
        $user = ServiceLocator::GetServer()->GetUserSession();

        $comment = $reservationSeries->Description()
            . ' This scan has been deleted by [user:' . $user . '] at [datetime:' . $now->format('Y-m-d H:i:s')
            . ']with [condition:' . $condition . '] and [heads-up:' . $headsUp . '] days.';

        $answers = $this->logbookAnswers($lsClient, $sessionKey, $current->ReferenceNumber(), [
            LogbookQuestions::COMMENTS => $comment,
            LogbookQuestions::SCAN_DONE => 'N',
            LogbookQuestions::UPDATE_7_DAYS => 'N',
            LogbookQuestions::UNF_TECHNICAL_ISSUE => 'N',
            LogbookQuestions::PROTOCOL => $reservationSeries->Title(),
            LogbookQuestions::OPERATOR => 'N/A',
            LogbookQuestions::RA => $this->researchAssociate($reservationSeries),
            LogbookQuestions::URL_ID => 0,
            LogbookQuestions::PATIENT_ID => $this->sessionName($reservationSeries),
            LogbookQuestions::CURR_DATE => 'Date: ' . $startDate->format('Y-m-d'),
            LogbookQuestions::START_TIME => $start->format('Y-m-d H:i'),
            LogbookQuestions::END_TIME => $end->format('Y-m-d H:i'),
        ]);

        $lsClient->addResponse(
            $sessionKey,
            MriPostResSettings::logbookSurveyId(),
            $answers
        );
    }
}
