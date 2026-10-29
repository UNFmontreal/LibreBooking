<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use Closure;
use DateTimeImmutable;
use LibreBooking\Limesurvey\Participant;
use LibreBooking\Mri\ParticipantDirectory;
use LibreBooking\Mri\ParticipantHandover;
use LibreBooking\Mri\ReservationFacts;
use LibreBooking\Mri\ScreeningPlanner;
use LibreBooking\Studies\StudyDefinition;
use LibreBooking\Studies\StudyMatch;
use LogicException;
use TestBase;

class ScreeningPlannerTest extends TestBase
{
    private const SESSION_ATTRIBUTE = 5;

    public function testNewSingleSessionCreatesItsParticipantAndShowsTheScreeningLink()
    {
        $context = $this->planner([])->forNewReservation($this->single('REF-1'));

        $this->assertTrue($context->createParticipant);
        $this->assertTrue($context->showScreeningLink);
        $this->assertFalse($context->screening->completed);
        $this->assertSame('ANT_001', $context->screening->participantName);
        $this->assertSame('REF-1', $context->screening->participantToken);
        $this->assertNull($context->handover);
        $this->assertNull($context->deleteParticipantId);
        $this->assertNull($context->previousStart);
    }

    public function testFirstVisitOfAMultiSessionStudyCreatesTheParticipantOfTheStudy()
    {
        $context = $this->planner([])->forNewReservation($this->multi('REF-2'));

        $this->assertTrue($context->createParticipant);
        $this->assertTrue($context->showScreeningLink);
        $this->assertTrue($context->screening->isMulti1);
        $this->assertSame('HOF_001', $context->screening->participantName);
        $this->assertSame('REF-2', $context->screening->participantToken);
    }

    public function testLaterVisitSharesTheParticipantOfTheStudy()
    {
        $directory = ['firstname:HOF_001' => new Participant('SHARED', '7', 'HOF_001', true)];

        $context = $this->planner($directory)->forNewReservation($this->multi('REF-3'));

        $this->assertFalse($context->createParticipant);
        $this->assertFalse($context->showScreeningLink);
        $this->assertTrue($context->screening->isMultiX);
        $this->assertTrue($context->screening->completed);
        $this->assertSame('SHARED', $context->screening->participantToken);
    }

    public function testPhantomReservationIsCompleteAndNeverLooksUpLimesurvey()
    {
        $directory = new class () implements ParticipantDirectory {
            public function participant(array $query): ?Participant
            {
                throw new LogicException('a phantom has no participant');
            }
        };

        $context = (new ScreeningPlanner($directory, self::SESSION_ATTRIBUTE))->forNewReservation($this->single('REF-1', phantom: true));

        $this->assertTrue($context->screening->completed);
        $this->assertFalse($context->createParticipant);
        $this->assertFalse($context->showScreeningLink);
        $this->assertNull($context->screening->participantToken);
    }

    public function testUnchangedRegularSessionShowsTheLinkUntilTheScreeningIsComplete()
    {
        $incomplete = ['token:REF-1' => new Participant('REF-1', '4', 'ANT_001', false), 'firstname:ANT_001' => new Participant('REF-1', '4', 'ANT_001', false)];
        $complete = ['token:REF-1' => new Participant('REF-1', '4', 'ANT_001', true), 'firstname:ANT_001' => new Participant('REF-1', '4', 'ANT_001', true)];

        $pending = $this->planner($incomplete)->forUpdate($this->single('REF-1'), $this->single('REF-1'), false);
        $done = $this->planner($complete)->forUpdate($this->single('REF-1'), $this->single('REF-1'), false);

        $this->assertTrue($pending->showScreeningLink);
        $this->assertFalse($pending->screening->completed);
        $this->assertFalse($pending->createParticipant);
        $this->assertFalse($done->showScreeningLink);
        $this->assertTrue($done->screening->completed);
    }

    public function testUpdateCarriesThePreviousTimes()
    {
        $old = $this->single('REF-1', start: new DateTimeImmutable('2026-11-01 10:00'), end: new DateTimeImmutable('2026-11-01 12:00'));
        $directory = ['token:REF-1' => new Participant('REF-1', '4', 'ANT_001', false), 'firstname:ANT_001' => new Participant('REF-1', '4', 'ANT_001', false)];

        $context = $this->planner($directory)->forUpdate($this->single('REF-1'), $old, false);

        $this->assertEquals(new DateTimeImmutable('2026-11-01 10:00'), $context->previousStart);
        $this->assertEquals(new DateTimeImmutable('2026-11-01 12:00'), $context->previousEnd);
    }

    public function testRenamedRegularSessionResetsItsParticipantUnderTheNewName()
    {
        $directory = ['token:REF-1' => new Participant('REF-1', '4', 'ANT_001', true)];

        $context = $this->planner($directory)->forUpdate($this->single('REF-1', 'ANT_002'), $this->single('REF-1', 'ANT_001'), false);

        $this->assertSame(['firstname' => 'ANT_002', 'completed' => 'N', 'usesleft' => 1], $context->participantUpdate);
        $this->assertTrue($context->deleteResponses);
        $this->assertTrue($context->showScreeningLink);
        $this->assertFalse($context->screening->completed);
        $this->assertFalse($context->createParticipant);
    }

    public function testRegularReservationBecomingPhantomDeletesItsParticipant()
    {
        $directory = ['token:REF-1' => new Participant('REF-1', '4', 'ANT_001', false), 'firstname:ANT_001' => new Participant('REF-1', '4', 'ANT_001', false)];

        $context = $this->planner($directory)->forUpdate($this->single('REF-1', phantom: true), $this->single('REF-1'), false);

        $this->assertSame('4', $context->deleteParticipantId);
        $this->assertTrue($context->deleteResponses);
        $this->assertTrue($context->screening->completed);
        $this->assertFalse($context->showScreeningLink);
    }

    public function testPhantomStayingPhantomIsCompleteAndCarriesNoPreviousTimes()
    {
        $old = $this->single('REF-1', phantom: true, start: new DateTimeImmutable('2026-11-01 10:00'), end: new DateTimeImmutable('2026-11-01 12:00'));

        $context = $this->planner([])->forUpdate($this->single('REF-1', phantom: true), $old, false);

        $this->assertTrue($context->screening->completed);
        $this->assertNull($context->previousStart);
    }

    public function testCancelledRegularReservationDeletesItsParticipant()
    {
        $directory = ['token:REF-1' => new Participant('REF-1', '4', 'ANT_001', false)];

        $context = $this->planner($directory)->forUpdate($this->single('REF-1'), $this->single('REF-1'), true);

        $this->assertSame('4', $context->deleteParticipantId);
        $this->assertTrue($context->deleteResponses);
        $this->assertFalse($context->showScreeningLink);
        $this->assertNull($context->previousStart);
    }

    public function testCancelledFirstVisitIsHandedOverToTheNextVisit()
    {
        $directory = ['token:REF-2' => new Participant('REF-2', '8', 'HOF_001', false)];

        $context = $this->planner($directory, fn (): ?string => 'REF-3')->forUpdate($this->multi('REF-2'), $this->multi('REF-2'), true);

        $this->assertEquals(new ParticipantHandover('REF-3', ParticipantHandover::TOKEN_AND_RESPONSE), $context->handover);
        $this->assertNull($context->deleteParticipantId);
    }

    public function testCancelledLastVisitDeletesTheParticipantOfTheStudy()
    {
        $directory = ['token:REF-2' => new Participant('REF-2', '8', 'HOF_001', false)];

        $context = $this->planner($directory)->forUpdate($this->multi('REF-2'), $this->multi('REF-2'), true);

        $this->assertNull($context->handover);
        $this->assertSame('8', $context->deleteParticipantId);
        $this->assertTrue($context->deleteResponses);
    }

    public function testDeletingARegularReservationRemovesItsParticipant()
    {
        $directory = ['token:REF-1' => new Participant('REF-1', '4', 'ANT_001', false)];

        $context = $this->planner($directory)->forDeletion($this->single('REF-1'));

        $this->assertSame('4', $context->deleteParticipantId);
        $this->assertTrue($context->deleteResponses);
        $this->assertNull($context->handover);
    }

    public function testDeletingAFirstVisitWithIncompleteScreeningMovesTheParticipantToTheNextVisit()
    {
        $directory = ['token:REF-2' => new Participant('REF-2', '8', 'HOF_001', false)];

        $context = $this->planner($directory, fn (): ?string => 'REF-3')->forDeletion($this->multi('REF-2'));

        $this->assertEquals(new ParticipantHandover('REF-3', ParticipantHandover::TOKEN), $context->handover);
        $this->assertTrue($context->deleteResponses);
        $this->assertNull($context->deleteParticipantId);
    }

    public function testDeletingAFirstVisitWithCompleteScreeningKeepsItsResponses()
    {
        $directory = ['token:REF-2' => new Participant('REF-2', '8', 'HOF_001', true)];

        $context = $this->planner($directory, fn (): ?string => 'REF-3')->forDeletion($this->multi('REF-2'));

        $this->assertEquals(new ParticipantHandover('REF-3', ParticipantHandover::LASTNAME), $context->handover);
        $this->assertFalse($context->deleteResponses);
        $this->assertNull($context->deleteParticipantId);
    }

    public function testDeletingAReservationWithoutParticipantDeletesNoParticipant()
    {
        $context = $this->planner([])->forDeletion($this->single('REF-1'));

        $this->assertNull($context->deleteParticipantId);
        $this->assertNull($context->handover);
    }

    /**
     * @param array<string, Participant> $participants keyed "token:REF-1" or "firstname:ANT_001"
     * @param (Closure(string, \DateTimeInterface, int[]): ?string)|null $next
     */
    private function planner(array $participants, ?Closure $next = null): ScreeningPlanner
    {
        $directory = new class ($participants) implements ParticipantDirectory {
            public function __construct(private array $participants)
            {
            }

            public function participant(array $query): ?Participant
            {
                return $this->participants[array_key_first($query) . ':' . reset($query)] ?? null;
            }
        };

        return new ScreeningPlanner($directory, self::SESSION_ATTRIBUTE, $next ?? fn (): ?string => null);
    }

    private function single(string $reference, string $session = 'ANT_001', bool $phantom = false, ?DateTimeImmutable $start = null, ?DateTimeImmutable $end = null): ReservationFacts
    {
        $study = new StudyMatch(new StudyDefinition('^ANT', false));

        return new ReservationFacts($reference, $session, $phantom, $study, [10], $start, $end);
    }

    private function multi(string $reference, string $session = 'HOF_001_pre1'): ReservationFacts
    {
        $study = new StudyMatch(new StudyDefinition('^HOF', true), 'HOF', '001', 'pre1');

        return new ReservationFacts($reference, $session, false, $study, [10], new DateTimeImmutable('2026-11-01 10:00'), new DateTimeImmutable('2026-11-01 12:00'));
    }
}
