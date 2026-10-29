<?php

declare(strict_types=1);

namespace LibreBooking\Studies;

/**
 * The study a name matched, and the parts of the name captured by its named groups.
 *
 * Study regexes may name these groups: "study" (the study prefix), "subject" (the participant) and
 * "session" (the visit or session within that participant). Missing groups are empty strings.
 */
final readonly class StudyMatch
{
    public function __construct(
        public StudyDefinition $study,
        public string $prefix = '',
        public string $subject = '',
        public string $session = '',
    ) {
    }

    /**
     * The screening participant that a multi-session study shares across its bookings: the study prefix and subject.
     * Returns null for single-session studies and for regexes that do not name a subject.
     */
    public function participantKey(): ?string
    {
        if (!$this->study->multisession || $this->subject === '') {
            return null;
        }

        return implode('_', array_filter([$this->prefix, $this->subject], 'strlen'));
    }
}
