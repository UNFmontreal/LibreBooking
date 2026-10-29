<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * Hands the screening participant of a multi-session reservation over to the participant's next reservation,
 * so the screening survives when the first visit is renamed, cancelled or deleted.
 */
final readonly class ParticipantHandover
{
    /**
     * The participant and its first response move to the next reservation's token.
     */
    public const TOKEN_AND_RESPONSE = 'token_and_response';

    /**
     * Only the participant moves to the next reservation's token; its responses are deleted separately.
     */
    public const TOKEN = 'token';

    /**
     * The completed screening stays on its token; the next reservation's token is recorded as the last name.
     */
    public const LASTNAME = 'lastname';

    public function __construct(
        public string $nextToken,
        public string $mode,
    ) {
    }
}
