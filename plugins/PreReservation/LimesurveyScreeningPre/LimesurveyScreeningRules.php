<?php

/**
 * The screening rules a reservation must pass, checked in this order. The first violation denies the reservation.
 *
 * Each message is shown to the user when its rule is violated.
 */
class LimesurveyScreeningRules
{
    public function __construct(
        private readonly LimesurveyParticipantGateway $gateway,
        private readonly string $phantomMultiSessionMessage,
        private readonly string $recurringSessionMessage,
        private readonly string $duplicateParticipantMessage,
        private readonly string $resourceChangeMessage,
    ) {
    }

    /**
     * @return string|null the message to deny the reservation with, or null when every rule passes
     */
    public function violation(LimesurveyScreeningRequest $request): ?string
    {
        if ($request->isPhantom && $request->isMulti) {
            return $this->phantomMultiSessionMessage;
        }
        if ($request->isRecurring && !$request->isMulti) {
            return $this->recurringSessionMessage;
        }
        if ($this->duplicatesParticipant($request)) {
            return $this->duplicateParticipantMessage;
        }
        if ($this->changesResources($request)) {
            return $this->resourceChangeMessage;
        }

        return null;
    }

    /**
     * A single-session study can only be booked once: its participant must not already exist in the screening survey.
     * Checked for new reservations, and for updates that give the reservation a new participant: a renamed session, or a
     * phantom reservation that becomes a regular one.
     */
    private function duplicatesParticipant(LimesurveyScreeningRequest $request): bool
    {
        if ($request->isMulti || !$this->needsNewParticipant($request)) {
            return false;
        }

        return $this->gateway->participantExists($request->participantName);
    }

    private function needsNewParticipant(LimesurveyScreeningRequest $request): bool
    {
        if (!$request->isUpdate) {
            return !$request->isPhantom;
        }

        $renamed = $request->previousSessionName !== '' && $request->previousSessionName !== $request->sessionName;
        $leavesPhantom = $request->previousIsPhantom && !$request->isPhantom;

        return $renamed || $leavesPhantom;
    }

    /**
     * An update may not move the reservation to other resources.
     */
    private function changesResources(LimesurveyScreeningRequest $request): bool
    {
        if (!$request->isUpdate || empty($request->previousResourceIds)) {
            return false;
        }

        $current = $request->resourceIds;
        $previous = $request->previousResourceIds;
        sort($current);
        sort($previous);

        return $current !== $previous;
    }
}
