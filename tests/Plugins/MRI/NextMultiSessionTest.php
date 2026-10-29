<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use DateTimeImmutable;
use LibreBooking\Mri\NextMultiSession;
use LibreBooking\Mri\ParticipantName;
use LibreBooking\Studies\StudyCatalog;
use LibreBooking\Studies\StudyDefinition;
use TestBase;

class NextMultiSessionTest extends TestBase
{
    private const AFTER = '2026-10-26 12:00:00';

    public function testFindsTheFirstLaterReservationOfTheParticipant()
    {
        $candidates = [
            [new DateTimeImmutable('2026-10-27 09:00'), 'REF-OTHER', 'HOF_002_pre1'],
            [new DateTimeImmutable('2026-10-28 09:00'), 'REF-NEXT', 'HOF_001_pre2'],
            [new DateTimeImmutable('2026-10-29 09:00'), 'REF-LATER', 'HOF_001_pre3'],
        ];

        $this->assertSame('REF-NEXT', NextMultiSession::pick('HOF_001', $this->after(), $candidates, $this->participantOf()));
    }

    public function testReservationsStartingBeforeTheEndAreIgnored()
    {
        $candidates = [
            [new DateTimeImmutable('2026-10-26 10:00'), 'REF-EARLIER', 'HOF_001_pre1'],
            [new DateTimeImmutable(self::AFTER), 'REF-SAME-TIME', 'HOF_001_pre2'],
        ];

        $this->assertNull(NextMultiSession::pick('HOF_001', $this->after(), $candidates, $this->participantOf()));
    }

    public function testNoMatchingReservationIsNull()
    {
        $this->assertNull(NextMultiSession::pick('HOF_009', $this->after(), [
            [new DateTimeImmutable('2026-10-28 09:00'), 'REF-1', 'HOF_001_pre2'],
        ], $this->participantOf()));
    }

    public function testEmptyParticipantNameMatchesNothing()
    {
        $this->assertNull(NextMultiSession::pick('', $this->after(), [
            [new DateTimeImmutable('2026-10-28 09:00'), 'REF-1', 'HOF_001_pre2'],
        ], $this->participantOf()));
    }

    public function testParticipantsWhoseSubjectsContainEachOtherAreNotMixedUp()
    {
        $candidates = [
            [new DateTimeImmutable('2026-10-28 09:00'), 'REF-CON', 'HOF_001CON_pre1'],
            [new DateTimeImmutable('2026-10-29 09:00'), 'REF-NEXT', 'HOF_001_pre2'],
        ];

        $this->assertSame('REF-NEXT', NextMultiSession::pick('HOF_001', $this->after(), $candidates, $this->participantOf()));
    }

    public function testSessionsOfNoStudyAreIgnored()
    {
        $candidates = [[new DateTimeImmutable('2026-10-28 09:00'), 'REF-1', 'unknown_HOF_001_pre2']];

        $this->assertNull(NextMultiSession::pick('HOF_001', $this->after(), $candidates, $this->participantOf()));
    }

    /**
     * @return callable(string): ?string the participant of a session name, from the study regex
     */
    private function participantOf(): callable
    {
        $catalog = StudyCatalog::fromStudies([
            new StudyDefinition('^(?P<study>HOF)_(?P<subject>[0-9]{3}(CON)?)_(?P<session>pre[0-9])$', true),
        ]);

        return function (string $sessionName) use ($catalog): ?string {
            $match = $catalog->find($sessionName);

            return $match === null ? null : ParticipantName::resolve($match, $sessionName);
        };
    }

    private function after(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::AFTER);
    }
}
