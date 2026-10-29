<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Limesurvey\Participant;
use LibreBooking\Mri\ScreeningState;
use LibreBooking\Studies\StudyDefinition;
use LibreBooking\Studies\StudyMatch;
use TestBase;

class ScreeningStateTest extends TestBase
{
    public function testSingleSessionIsScreenedUnderItsOwnReference()
    {
        $state = ScreeningState::derive($this->single(), 'ANT_001', 'REF-1', false, $this->participant('REF-1', true), null);

        $this->assertSame('REF-1', $state->participantToken);
        $this->assertTrue($state->completed);
        $this->assertFalse($state->isMulti);
        $this->assertSame('ETHIC-1', $state->ethic);
        $this->assertSame('ANT_001', $state->participantName);
    }

    public function testSingleSessionWithoutParticipantIsNotCompleted()
    {
        $state = ScreeningState::derive($this->single(), 'ANT_001', 'REF-1', false, null, null);

        $this->assertFalse($state->completed);
        $this->assertSame('REF-1', $state->participantToken);
    }

    public function testMultiSessionWithOwnParticipantIsFirstVisit()
    {
        $state = ScreeningState::derive($this->multi(), 'HOF_001_pre1', 'REF-2', false, $this->participant('REF-2', true), null);

        $this->assertTrue($state->isMulti1);
        $this->assertFalse($state->isMultiX);
        $this->assertTrue($state->completed);
        $this->assertSame('HOF_001', $state->participantName);
    }

    public function testMultiSessionWithoutOwnParticipantIsLaterVisit()
    {
        $state = ScreeningState::derive($this->multi(), 'HOF_001_pre2', 'REF-3', false, null, $this->participant('REF-1', false));

        $this->assertTrue($state->isMultiX);
        $this->assertFalse($state->isMulti1);
        $this->assertFalse($state->completed);
        $this->assertSame('REF-1', $state->participantToken);
    }

    public function testPhantomSingleSessionHasNoScreening()
    {
        $state = ScreeningState::derive($this->single(), 'ANT_001', 'REF-4', true, null, null);

        $this->assertNull($state->participantToken);
        $this->assertFalse($state->completed);
    }

    private function single(): StudyMatch
    {
        return new StudyMatch(new StudyDefinition('^ANT', false, 'gid', 'ETHIC-1'), 'ANT', '001');
    }

    private function multi(): StudyMatch
    {
        return new StudyMatch(new StudyDefinition('^HOF', true, 'gid', 'ETHIC-2'), 'HOF', '001', 'pre1');
    }

    private function participant(string $token, bool $completed): Participant
    {
        return new Participant($token, '1', 'ANT_001', $completed);
    }
}
