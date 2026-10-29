<?php

use LibreBooking\Mri\MriReservationContext;
use LibreBooking\Mri\ScreeningPlanner;

/**
 * Plans the LimeSurvey changes for the deletion of a reservation of a monitored resource and attaches them to the
 * series as an MriReservationContext for the post-reservation notifications.
 *
 * Screening never blocks a deletion: when LimeSurvey or the studies are unavailable, no context is attached.
 */
class LimesurveyScreeningDeletion implements IReservationValidationService
{
    private IReservationValidationService $serviceToDecorate;
    private LimesurveyScreeningSettings $settings;
    /**
     * @var callable(): \LibreBooking\Studies\StudyCatalog loads the studies when they are first needed
     */
    private $studies;
    private ScreeningPlanner $planner;

    public function __construct(
        IReservationValidationService $serviceToDecorate,
        LimesurveyScreeningSettings $settings,
        callable $studies,
        ScreeningPlanner $planner
    ) {
        $this->serviceToDecorate = $serviceToDecorate;
        $this->settings = $settings;
        $this->studies = $studies;
        $this->planner = $planner;
    }

    public function Validate($series, $retryParameters = null)
    {
        $result = $this->serviceToDecorate->Validate($series, $retryParameters);
        if (!$result->CanBeSaved()) {
            return $result;
        }

        $resourceIds = array_map('intval', $series->AllResourceIds());
        if (empty(array_intersect($resourceIds, $this->settings->monitoredResourceIds))) {
            return $result;
        }

        $sessionName = trim((string) $series->GetAttributeValue($this->settings->sessionNameAttributeId));
        $studies = ($this->studies)();
        if ($sessionName === '' || !$studies->isAvailable()) {
            Log::Error('LimesurveyScreeningPre could not plan the screening of a deleted reservation without a session name or studies');
            return $result;
        }

        $match = $studies->find($sessionName);
        if ($match === null) {
            return $result;
        }

        try {
            $facts = LimesurveyReservationFacts::fromSeries($series, $this->settings, $match, $sessionName);
            MriReservationContext::attach($series, $this->planner->forDeletion($facts));
        } catch (RuntimeException $ex) {
            Log::Error('LimesurveyScreeningPre could not plan the screening of a deleted reservation. Error=%s', $ex->getMessage());
        }

        return $result;
    }
}
