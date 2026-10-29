<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * The MRI settings the cron jobs use. Built once by the cron bootstrap from the MRI configuration.
 */
final readonly class Settings
{
    /**
     * @param string[] $monitoredResourceNames resources whose reservations are screened
     */
    public function __construct(
        public array $monitoredResourceNames,
        public string $lsBaseUrl,
        public int $screeningSurveyId,
        public int $logbookSurveyId,
        public int $lookAheadDays,
        public int $phantomAttributeId,
        public int $sessionNameAttributeId,
        public int $raAttributeId,
        public int $screeningLinkAttributeId,
        public int $logbookLinkAttributeId,
        public string $screeningLinkText,
        public string $completedText,
        public string $notCompletedText,
        public string $linkToLogbook,
        public string $logbookLinkText,
        public string $screeningNotCompletedText,
        public string $screeningEmailSubject,
        public string $screeningEmailBody,
        public string $logbookEmailSubject,
        public string $logbookEmailBody,
        public string $logbookOperatorAddress,
        public string $supportName,
        public string $dateFormat,
        public string $timeFormat,
    ) {
    }
}
