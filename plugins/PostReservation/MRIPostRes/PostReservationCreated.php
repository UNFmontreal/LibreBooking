<?php

use LibreBooking\Mri\MriMessages;
use LibreBooking\Mri\MriReservationContext;
use LibreBooking\Mri\ScreeningLinks;

/**
 * Post-reservation work for a newly created reservation.
 *
 * Registers the screening and logbook participants, sets the reservation status, and writes the screening and logbook
 * links shown on the reservation.
 */
class PostReservationCreated extends MRIPostResNotifier
{
    protected function process($reservationSeries, MriReservationContext $context): void
    {
        $this->withLimesurvey(function ($lsClient, string $sessionKey) use ($reservationSeries, $context): void {
            $surveyId = MriPostResSettings::screeningSurveyId();
            $instance = null;

            foreach ($reservationSeries->Instances() as $instance) {
                $this->ensureLogbookParticipant($lsClient, $sessionKey, $instance->ReferenceNumber());

                if ($context->createParticipant) {
                    $this->registerParticipant(
                        $lsClient,
                        $sessionKey,
                        $surveyId,
                        $context->screening->participantName,
                        $instance->ReferenceNumber(),
                        $context->caregiverScreening + 1
                    );
                }

                $status = $context->screening->completed ? ReservationStatus::Created : ReservationStatus::Pending;
                MriSeriesStatus::set($reservationSeries->SeriesId(), $status);

                if ($context->screening->completed) {
                    $this->storeCompletedScreening($reservationSeries, $instance, $context, $lsClient, $sessionKey, $surveyId);
                } else {
                    $this->storePendingScreening($reservationSeries, $instance, $context, $surveyId);
                }
            }

            if ($instance !== null) {
                MriOperator::syncForNewInstance($this->operatorUserId($reservationSeries), $instance->ReservationId());
            }
        });
    }

    private function storeCompletedScreening($reservationSeries, $instance, MriReservationContext $context, $lsClient, string $sessionKey, int $surveyId): void
    {
        $seriesId = $reservationSeries->SeriesId();

        if ($this->isPhantom($reservationSeries)) {
            Log::Debug('Validating screening because its a phantom');
            MriAttributeValues::set($seriesId, MriPostResSettings::screeningValidAttributeId(), '1');
            $responseIds = [0, 0];
        } else {
            $responseIds = $this->screeningResponseIds($lsClient, $sessionKey, $context->screening->participantName, $surveyId);
        }

        MriAttributeValues::set(
            $seriesId,
            MriPostResSettings::logbookLinkAttributeId(),
            $this->logbookLink($reservationSeries, $instance, $responseIds)
        );

        MriAttributeValues::replaceIfPresent(
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
            $url = ScreeningLinks::indexedSurveyTokenUrl($baseUrl, $surveyId, $token);
            echo MriMessages::text(MriMessages::SCREENING_LINK_PRE_TEXT)
                . '<a href="' . $url . '">' . $linkText . '</a><br>';

            MriAttributeValues::replaceIfPresent(
                $seriesId,
                MriPostResSettings::screeningLinkAttributeId(),
                ScreeningLinks::surveyTokenUrl($baseUrl, $surveyId, $token)
            );
            return;
        }

        $this->replaceScreeningLinkUnlessCompleted(
            $seriesId,
            $surveyId,
            ScreeningLinks::notCompletedText($linkText, MriMessages::text(MriMessages::NOT_COMPLETED_SURVEY_TEXT))
        );
    }
}
