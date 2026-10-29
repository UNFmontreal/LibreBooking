<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Limesurvey\Participant;
use TestBase;

class ParticipantTest extends TestBase
{
    public function testMissingParticipantIsNull()
    {
        $this->assertNull(Participant::fromProperties(null));
        $this->assertNull(Participant::fromProperties(['status' => 'No participant found']));
    }

    public function testUncompletedParticipantIsNotCompleted()
    {
        $participant = Participant::fromProperties(['token' => 'REF-1', 'tid' => '7', 'firstname' => 'ANT_001', 'completed' => 'N']);

        $this->assertSame('REF-1', $participant->token);
        $this->assertSame('7', $participant->tid);
        $this->assertFalse($participant->completed);
    }

    public function testMissingCompletionIsNotCompleted()
    {
        $this->assertFalse(Participant::fromProperties(['token' => 'REF-1'])->completed);
        $this->assertFalse(Participant::fromProperties(['token' => 'REF-1', 'completed' => ''])->completed);
        $this->assertFalse(Participant::fromProperties(['token' => 'REF-1', 'completed' => '0'])->completed);
    }

    public function testDatedCompletionIsCompleted()
    {
        $this->assertTrue(Participant::fromProperties(['token' => 'REF-1', 'completed' => '2026-10-01 10:00'])->completed);
    }
}
