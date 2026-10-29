<?php

/**
 * Which reservations are screened and the messages used when screening cannot complete.
 */
final readonly class LimesurveyScreeningSettings
{
    /**
     * @param int[] $monitoredResourceIds reservations touching any of these resources are screened
     */
    public function __construct(
        public array $monitoredResourceIds,
        public int $sessionNameAttributeId,
        public int $phantomAttributeId,
        public string $sessionNameRequiredMessage,
        public string $invalidSessionNameMessage,
        public string $unavailableMessage,
        public int $unfTechnicalIssueAttributeId = 0,
    ) {
    }
}
