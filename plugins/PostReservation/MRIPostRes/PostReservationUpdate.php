<?php

use LibreBooking\Mri\MriMessages;
use LibreBooking\Mri\LogbookQuestions;
use LibreBooking\Mri\LogbookUpdate;
use LibreBooking\Mri\MriReservationContext;
use LibreBooking\Mri\ScreeningLinks;
use LibreBooking\Mri\MriTime;

/**
 * Post-reservation work for an updated reservation.
 *
 * Keeps the operator, LimeSurvey participant and responses, logbook entry and screening links in step with the
 * change. Responses are cleaned up when the screening was repeated or the resource changed.
 */
class PostReservationUpdate extends MRIPostResNotifier
{
    /**
     * Columns requested when looking up a participant.
     */

    /**
     * Logbook tokens are zero padded to this length.
     */
    private const LOGBOOK_TOKEN_LENGTH = 23;

    private const LOGBOOK_TIME_FORMAT = 'Y-m-d H:i';

    protected function process($reservationSeries, MriReservationContext $context): void
    {
        $this->withLimesurvey(function ($lsClient, string $sessionKey) use ($reservationSeries, $context): void {
            $surveyId = MriPostResSettings::screeningSurveyId();
            $seriesId = $reservationSeries->SeriesId();
            $cancelled = $this->hasTechnicalIssue($reservationSeries);
            $reference = $reservationSeries->CurrentInstance()->ReferenceNumber();

            // the participant changes decided before the save come first, as the later steps work on the result
            if ($context->handover !== null) {
                $this->applyHandover($lsClient, $sessionKey, $surveyId, $reference, $context->handover);
            }
            if ($context->participantUpdate !== null) {
                $lsClient->setParticipantProperties($sessionKey, $surveyId, ['token' => $reference], $context->participantUpdate);
            }

            $this->addLogbookEntry($lsClient, $sessionKey, $reservationSeries, $context, $cancelled);

            foreach ($reservationSeries->Instances() as $instance) {
                if ($this->isPhantom($reservationSeries)) {
                    Log::Debug('Validating screening because its a phantom');
                    MriAttributeValues::set($seriesId, MriPostResSettings::screeningValidAttributeId(), '1');
                }

                $status = $context->screening->completed ? ReservationStatus::Created : ReservationStatus::Pending;
                MriSeriesStatus::set($seriesId, $status);

                if (!$cancelled) {
                    MriOperator::syncForInstance($this->operatorUserId($reservationSeries), $instance->ReservationId());
                }

                if ($context->createParticipant) {
                    // a caregiver screening can be used twice
                    $this->registerParticipant(
                        $lsClient,
                        $sessionKey,
                        $surveyId,
                        $context->screening->participantName,
                        $instance->ReferenceNumber(),
                        $context->caregiverScreening ? 2 : 1
                    );
                }

                if ($context->deleteResponses) {
                    foreach ($lsClient->responseIds($sessionKey, $surveyId, $instance->ReferenceNumber()) as $responseId) {
                        $lsClient->deleteResponse($sessionKey, $surveyId, $responseId);
                    }
                }

                if ($context->deleteParticipantId !== null) {
                    $lsClient->deleteParticipants($sessionKey, $surveyId, [$context->deleteParticipantId]);
                }

                if ($context->screening->completed) {
                    $this->storeCompletedScreening($reservationSeries, $instance, $lsClient, $sessionKey, $context, $surveyId);
                } else {
                    $this->storePendingScreening($reservationSeries, $instance, $context, $surveyId);
                }
            }
        });
    }

    /**
     * Adds the logbook entry for this update, when there is one.
     *
     * A technical issue is recorded against the reservation's own logbook participant. A reservation that
     * was moved or shortened shortly before it starts gets a new logbook participant and records the previous times.
     */
    private function addLogbookEntry($lsClient, string $sessionKey, $series, MriReservationContext $context, bool $cancelled): void
    {
        $current = $series->CurrentInstance();
        $reference = $current->ReferenceNumber();
        $start = MriTime::fromDate($current->StartDate());
        $end = MriTime::fromDate($current->EndDate());
        $logbookSurveyId = MriPostResSettings::logbookSurveyId();

        if ($cancelled) {
            $token = $reference;
            $this->ensureLogbookParticipant($lsClient, $sessionKey, $token);
            $comments = $series->Description() . ' Associated Token: ' . $reference;
            $startDate = 'Date: ' . (clone $start)->setTime(0, 0, 0)->format('Y-m-d');
            $startTime = $start->format(self::LOGBOOK_TIME_FORMAT);
            $endTime = $end->format(self::LOGBOOK_TIME_FORMAT);
            $scanDone = 'N';
            $technicalIssue = 'Y';
            $updated = 'N';
        } else {
            if ($context->previousStart === null || $context->previousEnd === null) {
                return;
            }
            $update = LogbookUpdate::detect($context->previousStart, $context->previousEnd, $start, $end, new DateTime('NOW'));
            if ($update === null) {
                return;
            }

            $token = $this->nextFreeLogbookToken($lsClient, $sessionKey, $logbookSurveyId);
            $lsClient->addParticipants($sessionKey, $logbookSurveyId, [['token' => $token]]);
            $comments = $series->Description() . $update->commentSuffix . ' Associated Token: ' . $reference;
            $startDate = 'Date: ' . $context->previousStart->format('Y-m-d');
            $startTime = $context->previousStart->format(self::LOGBOOK_TIME_FORMAT);
            $endTime = $update->endTime;
            $scanDone = 'N';
            $technicalIssue = 'N';
            $updated = 'Y';
        }

        $answers = $this->logbookAnswers($lsClient, $sessionKey, $token, [
            LogbookQuestions::COMMENTS => $comments,
            LogbookQuestions::SCAN_DONE => $scanDone,
            LogbookQuestions::UNF_TECHNICAL_ISSUE => $technicalIssue,
            LogbookQuestions::UPDATE_7_DAYS => $updated,
            LogbookQuestions::PROTOCOL => $series->Title(),
            LogbookQuestions::OPERATOR => $this->operatorName($series),
            LogbookQuestions::RA => $this->researchAssociate($series),
            LogbookQuestions::URL_ID => 0,
            LogbookQuestions::PATIENT_ID => $this->sessionName($series),
            LogbookQuestions::CURR_DATE => $startDate,
            LogbookQuestions::START_TIME => $startTime,
            LogbookQuestions::END_TIME => $endTime,
        ]);
        $lsClient->addResponse($sessionKey, $logbookSurveyId, $answers);

        if ($cancelled) {
            $lsClient->setParticipantProperties(
                $sessionKey,
                $logbookSurveyId,
                ['token' => $token],
                ['completed' => 'Y', 'usesleft' => 0]
            );
        }
    }

    /**
     * The first logbook token, from 1 upwards, that LimeSurvey does not know yet.
     */
    private function nextFreeLogbookToken($lsClient, string $sessionKey, int $logbookSurveyId): string
    {
        $number = 1;
        $participant = $lsClient->participant(
            $sessionKey,
            $logbookSurveyId,
            ['token' => self::padToken($number)]
        );

        while (isset($participant['token'])) {
            $number++;
            $participant = $lsClient->participant(
                $sessionKey,
                $logbookSurveyId,
                ['token' => self::padToken($number)]
            );
        }

        return self::padToken($number);
    }

    private function storeCompletedScreening($reservationSeries, $instance, $lsClient, string $sessionKey, MriReservationContext $context, int $surveyId): void
    {
        $seriesId = $reservationSeries->SeriesId();

        // the logbook link opens the logbook of this reservation's token, so the participant has to exist
        $this->ensureLogbookParticipant($lsClient, $sessionKey, $instance->ReferenceNumber());
        $responseIds = $this->isPhantom($reservationSeries)
            ? [0, 0]
            : $this->screeningResponseIds($lsClient, $sessionKey, $context->screening->participantName, $surveyId, true);

        MriAttributeValues::set(
            $seriesId,
            MriPostResSettings::logbookLinkAttributeId(),
            $this->logbookLink($reservationSeries, $instance, $responseIds)
        );

        MriAttributeValues::set(
            $seriesId,
            MriPostResSettings::screeningLinkAttributeId(),
            $this->completedScreeningLink($surveyId, $responseIds)
        );
    }

    private function storePendingScreening($reservationSeries, $instance, MriReservationContext $context, int $surveyId): void
    {
        $seriesId = $reservationSeries->SeriesId();
        $linkText = MriMessages::text(MriMessages::SCREENING_LINK_TEXT);
        $token = $instance->ReferenceNumber();
        $baseUrl = MriPostResSettings::lsBaseUrl();

        if ($context->showScreeningLink) {
            $url = ScreeningLinks::surveyTokenUrl($baseUrl, $surveyId, $token);
            echo '<br>' . MriMessages::text(MriMessages::SCREENING_LINK_PRE_TEXT)
                . '<a href="' . $url . '">' . $linkText . '</a><br>';

            MriAttributeValues::set(
                $seriesId,
                MriPostResSettings::screeningLinkAttributeId(),
                ScreeningLinks::surveyTokenUrl($baseUrl, $surveyId, $token)
            );
            return;
        }

        MriAttributeValues::set(
            $seriesId,
            MriPostResSettings::screeningLinkAttributeId(),
            ScreeningLinks::notCompletedText(
                $linkText,
                MriMessages::text(MriMessages::NOT_COMPLETED_SURVEY_TEXT)
            )
        );
    }

    private static function padToken(int $number): string
    {
        return str_pad((string) $number, self::LOGBOOK_TOKEN_LENGTH, '0', STR_PAD_LEFT);
    }
}
