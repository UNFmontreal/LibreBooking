<?php

declare(strict_types=1);

namespace LibreBooking\Studies;

/**
 * One study entry from a studies definition file.
 */
final readonly class StudyDefinition
{
    public function __construct(
        public string $regex,
        public bool $multisession,
        public ?string $gidName = null,
        public ?string $ethic = null,
        public int $caregiverScreening = 0,
    ) {
    }
}
