<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\ResourceNames;
use TestBase;

class ResourceNamesTest extends TestBase
{
    public function testSplitsCommaSeparatedNames()
    {
        $this->assertSame(['IRM 3T', 'EEG'], ResourceNames::parse('IRM 3T,EEG'));
    }

    public function testIgnoresSpacesAroundSeparators()
    {
        $this->assertSame(['IRM 3T', 'EEG', 'MEG'], ResourceNames::parse('IRM 3T , EEG, MEG'));
    }

    public function testEmptySettingMonitorsNothing()
    {
        $this->assertSame([], ResourceNames::parse(''));
        $this->assertSame([], ResourceNames::parse(' , '));
    }
}
