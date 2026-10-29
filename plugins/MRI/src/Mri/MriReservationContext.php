<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use DateTimeInterface;
use WeakMap;

/**
 * What the MRI pre-reservation validation hands to the post-reservation notifications.
 *
 * It carries only what the notifications cannot ask LibreBooking for once the reservation is saved: the screening
 * state found in LimeSurvey and the studies definitions, the LimeSurvey changes the validation decided on, and the
 * times of the reservation before an update. The notifications read everything else from the reservation itself.
 *
 * The context is attached to the reservation series for the rest of the request, so no property is written to the
 * series object (dynamic properties are deprecated in PHP 8.2).
 */
final class MriReservationContext
{
    /** @var WeakMap<object, MriReservationContext>|null */
    private static ?WeakMap $registry = null;

    /**
     * @param ScreeningState $screening the screening state after the change; for a phantom reservation it is complete
     * @param array<string, mixed>|null $participantUpdate properties to set on the participant of the reservation's token
     * @param DateTimeInterface|null $previousStart start before the update, in the MRI site time zone
     * @param DateTimeInterface|null $previousEnd end before the update, in the MRI site time zone
     */
    public function __construct(
        public readonly ScreeningState $screening,
        public readonly bool $showScreeningLink,
        public readonly int $caregiverScreening = 0,
        public readonly bool $createParticipant = false,
        public readonly bool $deleteResponses = false,
        public readonly ?string $deleteParticipantId = null,
        public readonly ?array $participantUpdate = null,
        public readonly ?ParticipantHandover $handover = null,
        public readonly ?DateTimeInterface $previousStart = null,
        public readonly ?DateTimeInterface $previousEnd = null,
    ) {
    }

    /**
     * Associates a context with a reservation series for the rest of the current request.
     * The reference is weak, so the entry disappears together with the series object.
     */
    public static function attach(object $series, self $context): void
    {
        self::$registry ??= new WeakMap();
        self::$registry[$series] = $context;
    }

    /**
     * Returns the context attached to a reservation series during this request, if any.
     */
    public static function for(object $series): ?self
    {
        return self::$registry[$series] ?? null;
    }
}
