<?php

declare(strict_types=1);

namespace LibreBooking\Limesurvey;

/**
 * A screening participant as LimeSurvey reports it.
 */
final readonly class Participant
{
    public function __construct(
        public string $token,
        public string $tid,
        public string $firstname,
        public bool $completed,
    ) {
    }

    /**
     * @param array<string, mixed>|null $properties the result of get_participant_properties
     */
    public static function fromProperties(?array $properties): ?self
    {
        if ($properties === null || !isset($properties['token'])) {
            return null;
        }

        // LimeSurvey reports 'N' (or nothing) until the screening is submitted
        $completed = (string) ($properties['completed'] ?? '');

        return new self(
            (string) $properties['token'],
            (string) ($properties['tid'] ?? ''),
            (string) ($properties['firstname'] ?? ''),
            $completed !== '' && $completed !== 'N' && $completed !== '0',
        );
    }
}
