<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\ParticipantName;
use LibreBooking\Studies\StudyCatalog;
use LibreBooking\Studies\StudyDefinition;
use LibreBooking\Studies\StudyMatch;
use TestBase;

class ParticipantNameTest extends TestBase
{
    public function testSingleSessionUsesTheWholeName()
    {
        $match = new StudyMatch(new StudyDefinition('x', false), 'ANT', '001');

        $this->assertSame('ANT_001_extra', ParticipantName::resolve($match, 'ANT_001_extra'));
    }

    public function testMultiSessionParticipantIsTheStudyAndSubjectOfTheRegex()
    {
        $match = new StudyMatch(new StudyDefinition('x', true), 'HOF', '001', 'pre1');

        $this->assertSame('HOF_001', ParticipantName::resolve($match, 'HOF_001_pre1'));
    }

    public function testMultiSessionWithoutStudyGroupUsesTheSubjectOnly()
    {
        $match = new StudyMatch(new StudyDefinition('x', true), '', 'p01', 'liris001');

        $this->assertSame('p01', ParticipantName::resolve($match, 'p01_liris001'));
    }

    public function testMultiSessionWithoutSubjectGroupUsesTheWholeName()
    {
        $match = new StudyMatch(new StudyDefinition('x', true));

        $this->assertSame('HOF_001_pre1', ParticipantName::resolve($match, 'HOF_001_pre1'));
    }

    public function testSubjectsThatContainEachOtherAreDifferentParticipants()
    {
        $catalog = StudyCatalog::fromStudies([
            new StudyDefinition('^(?P<study>HOF)_(?P<subject>[0-9]{3}(CON)?)_(?P<session>pre1|pre2)$', true),
        ]);

        $this->assertSame('HOF_001', ParticipantName::resolve($catalog->find('HOF_001_pre1'), 'HOF_001_pre1'));
        $this->assertSame('HOF_001CON', ParticipantName::resolve($catalog->find('HOF_001CON_pre1'), 'HOF_001CON_pre1'));
    }
}
