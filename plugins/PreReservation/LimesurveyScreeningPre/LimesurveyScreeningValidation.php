<?php

use LibreBooking\Mri\MriReservationContext;
use LibreBooking\Mri\ParticipantName;
use LibreBooking\Mri\ScreeningPlanner;
use LibreBooking\Studies\StudyCatalog;
use LibreBooking\Studies\StudyMatch;

/**
 * Runs the screening rules for reservations that touch a monitored resource.
 * A session name that matches no study is rejected.
 *
 * Reservations that pass are planned: the screening state and the LimeSurvey changes that follow from the change are
 * attached to the series as an MriReservationContext for the post-reservation notifications.
 */
class LimesurveyScreeningValidation implements IReservationValidationService
{
    private IReservationValidationService $serviceToDecorate;
    private LimesurveyScreeningSettings $settings;
    private bool $isUpdate;

    /**
     * @var callable(): StudyCatalog loads the studies when they are first needed
     */
    private $studies;
    private LimesurveyScreeningRules $rules;
    private IReservationRepository $reservations;
    private ?ScreeningPlanner $planner;

    /**
     * @param ScreeningPlanner|null $planner plans the LimeSurvey changes; without one no context is attached
     */
    public function __construct(
        IReservationValidationService $serviceToDecorate,
        LimesurveyScreeningSettings $settings,
        bool $isUpdate,
        callable $studies,
        LimesurveyScreeningRules $rules,
        IReservationRepository $reservations,
        ?ScreeningPlanner $planner = null
    ) {
        $this->serviceToDecorate = $serviceToDecorate;
        $this->settings = $settings;
        $this->isUpdate = $isUpdate;
        $this->studies = $studies;
        $this->rules = $rules;
        $this->reservations = $reservations;
        $this->planner = $planner;
    }

    public function Validate($series, $retryParameters = null)
    {
        $result = $this->serviceToDecorate->Validate($series, $retryParameters);

        // don't bother screening this reservation if others have failed
        if (!$result->CanBeSaved()) {
            return $result;
        }

        $resourceIds = array_map('intval', $series->AllResourceIds());
        if (empty(array_intersect($resourceIds, $this->settings->monitoredResourceIds))) {
            Log::Debug(
                'LimesurveyScreeningPre skips a reservation of other resources. Resources=%s, Monitored=%s',
                implode(',', $resourceIds),
                implode(',', $this->settings->monitoredResourceIds)
            );
            return $result;
        }

        $sessionName = trim((string) $series->GetAttributeValue($this->settings->sessionNameAttributeId));
        if ($sessionName === '') {
            return $this->denied($this->settings->sessionNameRequiredMessage);
        }

        $studies = ($this->studies)();
        if (!$studies->isAvailable()) {
            return $this->denied($this->settings->unavailableMessage);
        }

        Log::Debug('LimesurveyScreeningPre screens a reservation. SessionName=%s, Update=%s', $sessionName, $this->isUpdate ? 'yes' : 'no');

        $match = $studies->find($sessionName);
        if ($match === null) {
            return $this->denied($this->settings->invalidSessionNameMessage);
        }

        $stored = $this->isUpdate ? $this->storedReservation($series) : null;
        $study = $match->study;
        $request = new LimesurveyScreeningRequest(
            isUpdate: $this->isUpdate,
            sessionName: $sessionName,
            participantName: ParticipantName::resolve($match, $sessionName),
            isMulti: $study->multisession,
            isPhantom: (bool) $series->GetAttributeValue($this->settings->phantomAttributeId),
            isRecurring: $series->IsRecurring(),
            resourceIds: $resourceIds,
            previousResourceIds: $stored === null ? [] : array_map('intval', $stored->AllResourceIds()),
            previousSessionName: $stored === null ? '' : trim((string) $stored->GetAttributeValue($this->settings->sessionNameAttributeId)),
            previousIsPhantom: $stored !== null && (bool) $stored->GetAttributeValue($this->settings->phantomAttributeId),
            unfTechnicalIssue: (bool) $series->GetAttributeValue($this->settings->unfTechnicalIssueAttributeId),
        );

        try {
            $message = $this->rules->violation($request);
            if ($message !== null) {
                return $this->denied($message);
            }

            if ($this->planner !== null) {
                MriReservationContext::attach($series, $this->plan($series, $stored, $match, $studies, $request));
            }
        } catch (RuntimeException $ex) {
            Log::Error('LimesurveyScreeningPre could not complete screening. Error=%s', $ex->getMessage());
            return $this->denied($this->settings->unavailableMessage);
        }

        return $result;
    }

    private function plan($series, $stored, StudyMatch $match, StudyCatalog $studies, LimesurveyScreeningRequest $request): MriReservationContext
    {
        $new = LimesurveyReservationFacts::fromSeries($series, $this->settings, $match, $request->sessionName);
        if ($stored === null) {
            return $this->planner->forNewReservation($new);
        }

        $previousName = $request->previousSessionName;
        $old = LimesurveyReservationFacts::fromSeries($stored, $this->settings, $previousName === '' ? null : $studies->find($previousName), $previousName);

        return $this->planner->forUpdate($new, $old, $request->unfTechnicalIssue);
    }

    /**
     * The reservation as it is stored before this update, or null when there is none.
     *
     * @return ExistingReservationSeries|object|null
     */
    private function storedReservation($series)
    {
        return $this->reservations->LoadByReferenceNumber($series->CurrentInstance()->ReferenceNumber());
    }

    private function denied(string $message): ReservationValidationResult
    {
        return new ReservationValidationResult(false, [$message]);
    }
}
