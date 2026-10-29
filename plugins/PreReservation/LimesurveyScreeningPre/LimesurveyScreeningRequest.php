<?php

/**
 * What the screening rules need to know about a reservation that is being saved.
 */
final readonly class LimesurveyScreeningRequest
{
    /**
     * @param int[] $resourceIds resources of the submitted reservation
     * @param int[] $previousResourceIds resources of the stored reservation; empty for new reservations
     * @param string $previousSessionName session name of the stored reservation; empty for new reservations
     */
    public function __construct(
        public bool $isUpdate,
        public string $sessionName,
        public string $participantName,
        public bool $isMulti,
        public bool $isPhantom,
        public bool $isRecurring,
        public array $resourceIds,
        public array $previousResourceIds,
        public string $previousSessionName = '',
        public bool $previousIsPhantom = false,
        public bool $unfTechnicalIssue = false,
    ) {
    }
}
