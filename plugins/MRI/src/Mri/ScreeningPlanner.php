<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use Closure;
use LibreBooking\Limesurvey\Participant;

/**
 * Works out, from LimeSurvey and the studies definitions, what the screening of a reservation looks like once a
 * change is saved and which LimeSurvey changes the post-reservation notifications must make.
 *
 * The plan only reads from LimeSurvey. The changes are carried to the notifications by the MriReservationContext.
 */
final class ScreeningPlanner
{
    private const NO_CHANGES = ['deleteResponses' => false, 'deleteId' => null, 'update' => null, 'handover' => null];

    /**
     * @param (Closure(string, \DateTimeInterface, int[]): ?string)|null $nextReference finds the next reservation of
     *        a multi-session participant; defaults to searching LibreBooking
     * @param (Closure(string): ?string)|null $participantOf the participant name of a session name, null when no study
     *        matches; needed to search LibreBooking for the next reservation
     */
    public function __construct(
        private readonly ParticipantDirectory $participants,
        private readonly int $sessionNameAttributeId,
        private readonly ?Closure $nextReference = null,
        private readonly ?Closure $participantOf = null,
    ) {
    }

    public function forNewReservation(ReservationFacts $new): MriReservationContext
    {
        return $this->finish($new, $this->baseline($new) + self::NO_CHANGES, null);
    }

    /**
     * @param bool $cancelsScreening true when the update marks the reservation as a technical issue
     */
    public function forUpdate(ReservationFacts $new, ReservationFacts $old, bool $cancelsScreening): MriReservationContext
    {
        $plan = $this->baseline($new) + self::NO_CHANGES;
        $oldParticipant = $this->participants->participant(['token' => $new->reference]);

        if ($cancelsScreening) {
            return $this->finish($new, $this->planCancelledScreening($plan, $new, $old, $oldParticipant), null);
        }

        if ($new->isPhantom && $old->isPhantom) {
            $plan['completed'] = true;
            $plan['show'] = false;

            return $this->finish($new, $plan, null);
        }

        $plan = $new->sessionName !== $old->sessionName
            ? $this->planRenamedSession($plan, $new, $old, $oldParticipant)
            : $this->planUnchangedSession($plan, $new, $old, $oldParticipant);

        return $this->finish($new, $plan, $old);
    }

    public function forDeletion(ReservationFacts $old): MriReservationContext
    {
        $participant = $this->participants->participant(['token' => $old->reference]);
        $plan = [
            'completed' => $participant?->completed ?? false,
            'show' => false,
            'create' => false,
            'multi1' => $old->isMulti() && $participant !== null,
            'multiX' => false,
            'byName' => null,
            'deleteResponses' => true,
            'deleteId' => null,
            'update' => null,
            'handover' => null,
        ];

        if ($participant === null) {
            return $this->finish($old, $plan, null);
        }

        $next = $old->isMulti() ? $this->nextReferenceOf($old) : null;
        if ($next === null) {
            $plan['deleteId'] = self::idOf($participant);
        } elseif ($participant->completed) {
            // the completed screening stays on its token: only the next token is recorded
            $plan['deleteResponses'] = false;
            $plan['handover'] = new ParticipantHandover($next, ParticipantHandover::LASTNAME);
        } else {
            $plan['handover'] = new ParticipantHandover($next, ParticipantHandover::TOKEN);
        }

        return $this->finish($old, $plan, null);
    }

    /**
     * The screening state of a reservation as it would be created.
     *
     * @return array<string, mixed>
     */
    private function baseline(ReservationFacts $facts): array
    {
        if ($facts->isPhantom) {
            return ['completed' => true, 'show' => false, 'create' => false, 'multi1' => false, 'multiX' => false, 'byName' => null];
        }

        $byName = $this->participants->participant(['firstname' => $facts->participantName()]);
        $completed = $byName?->completed ?? false;

        if (!$facts->isMulti()) {
            return ['completed' => $completed, 'show' => !$completed, 'create' => $byName === null, 'multi1' => false, 'multiX' => false, 'byName' => $byName];
        }

        // a multi-session reservation without a participant is the first visit, later visits share its participant
        $isFirstVisit = $byName === null;

        return ['completed' => $completed, 'show' => $isFirstVisit, 'create' => $isFirstVisit, 'multi1' => $isFirstVisit, 'multiX' => !$isFirstVisit, 'byName' => $byName];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function planCancelledScreening(array $plan, ReservationFacts $new, ReservationFacts $old, ?Participant $oldParticipant): array
    {
        if ($old->isMulti() && $oldParticipant !== null) {
            $next = $this->nextReferenceOf($old);
            if ($next !== null) {
                $plan['handover'] = new ParticipantHandover($next, ParticipantHandover::TOKEN_AND_RESPONSE);
            } else {
                $plan['deleteId'] = self::idOf($oldParticipant);
                $plan['deleteResponses'] = true;
            }
        } elseif (!$new->isPhantom && !$new->isMulti()) {
            $plan['deleteId'] = self::idOf($oldParticipant);
            $plan['deleteResponses'] = true;
        }
        $plan['show'] = false;

        return $plan;
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function planRenamedSession(array $plan, ReservationFacts $new, ReservationFacts $old, ?Participant $oldParticipant): array
    {
        /** @var Participant|null $byName */
        $byName = $plan['byName'];
        $oldWasFirstVisit = false;

        if ($oldParticipant !== null) {
            if ($old->isMulti()) {
                $oldWasFirstVisit = true;
                $next = $this->nextReferenceOf($old);
                if ($next !== null) {
                    $plan['handover'] = new ParticipantHandover($next, ParticipantHandover::TOKEN_AND_RESPONSE);
                } elseif ($new->isMulti() && $byName === null) {
                    // first visit renamed to another first visit: keep the participant under its new name
                    $plan['deleteResponses'] = true;
                    $plan['update'] = ['firstname' => $new->participantName(), 'completed' => 'N', 'usesleft' => 1];
                } else {
                    $plan['deleteId'] = self::idOf($oldParticipant);
                    $plan['deleteResponses'] = true;
                }
            } elseif ((!$new->isPhantom && !$new->isMulti()) || ($new->isMulti() && $byName !== null)) {
                $plan['deleteResponses'] = true;
                $plan['update'] = ['firstname' => $new->participantName(), 'completed' => 'N', 'usesleft' => $new->caregiverScreening() + 1];
            }
        }

        if ($new->isPhantom) {
            $plan['show'] = false;
            $plan['completed'] = true;
            $plan['create'] = false;
        } elseif (!$new->isMulti()) {
            $plan['show'] = true;
            $plan['completed'] = false;
            // the old first visit or phantom had no participant of its own to rename
            $plan['create'] = $old->isMulti() || $old->isPhantom;
        } elseif ($byName !== null) {
            $plan['show'] = false;
            $plan['create'] = false;
        } else {
            $plan['show'] = true;
            $plan['create'] = !($oldWasFirstVisit || (!$old->isMulti() && !$old->isPhantom));
        }

        return $plan;
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function planUnchangedSession(array $plan, ReservationFacts $new, ReservationFacts $old, ?Participant $oldParticipant): array
    {
        if ($old->isPhantom && !$new->isPhantom && !$new->isMulti()) {
            $plan['show'] = true;
            $plan['completed'] = false;
            $plan['create'] = true;
        } elseif (!$old->isPhantom && $new->isPhantom) {
            $plan['show'] = false;
            $plan['completed'] = true;
            $plan['create'] = false;
            $plan['deleteId'] = self::idOf($oldParticipant);
            $plan['deleteResponses'] = true;
        } elseif (!$new->isPhantom && !$new->isMulti()) {
            $plan['completed'] = $oldParticipant?->completed ?? false;
            $plan['show'] = !$plan['completed'];
        } elseif ($oldParticipant !== null) {
            $plan['show'] = true;
        } elseif ($plan['byName'] === null) {
            // neither a first visit nor a later one: the participant has to be created
            $plan['create'] = true;
            $plan['completed'] = false;
            $plan['show'] = true;
        } else {
            $plan['show'] = false;
        }

        return $plan;
    }

    /**
     * @param array<string, mixed> $plan
     * @param ReservationFacts|null $previous the stored reservation, when its times are needed for the logbook
     */
    private function finish(ReservationFacts $facts, array $plan, ?ReservationFacts $previous): MriReservationContext
    {
        /** @var Participant|null $byName */
        $byName = $plan['byName'] ?? null;
        $token = $facts->isPhantom ? null : ($plan['multiX'] ? $byName?->token : $facts->reference);
        $screening = new ScreeningState(
            $token,
            $plan['completed'],
            $facts->isMulti(),
            $plan['multi1'],
            $plan['multiX'],
            $facts->participantName(),
            $facts->ethic(),
        );

        return new MriReservationContext(
            $screening,
            $plan['show'],
            $facts->caregiverScreening(),
            $plan['create'],
            $plan['deleteResponses'],
            $plan['deleteId'],
            $plan['update'],
            $plan['handover'],
            $previous?->start,
            $previous?->end,
        );
    }

    private function nextReferenceOf(ReservationFacts $old): ?string
    {
        if ($old->end === null) {
            return null;
        }

        $name = $old->participantName();

        return $this->nextReference !== null
            ? ($this->nextReference)($name, $old->end, $old->resourceIds)
            : NextMultiSession::referenceAfter(
                $name,
                $old->end,
                $this->sessionNameAttributeId,
                $old->resourceIds,
                $this->participantOf ?? throw new \LogicException('The planner needs participantOf to find the next session')
            );
    }

    private static function idOf(?Participant $participant): ?string
    {
        return $participant !== null && $participant->tid !== '' ? $participant->tid : null;
    }
}
