<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use DateTimeInterface;
use LibreBooking\Studies\StudyMatch;

/**
 * What the screening plan needs to know about one state of a reservation: the submitted one or the stored one.
 */
final readonly class ReservationFacts
{
    /**
     * @param int[] $resourceIds
     * @param StudyMatch|null $study null when the session name matches no study
     */
    public function __construct(
        public string $reference,
        public string $sessionName,
        public bool $isPhantom,
        public ?StudyMatch $study,
        public array $resourceIds,
        public ?DateTimeInterface $start = null,
        public ?DateTimeInterface $end = null,
    ) {
    }

    public function isMulti(): bool
    {
        return $this->study?->study->multisession ?? false;
    }

    public function participantName(): string
    {
        return $this->study !== null ? ParticipantName::resolve($this->study, $this->sessionName) : $this->sessionName;
    }

    public function caregiverScreening(): int
    {
        return $this->study?->study->caregiverScreening ?? 0;
    }

    public function ethic(): ?string
    {
        return $this->study?->study->ethic;
    }
}
