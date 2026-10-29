<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Studies\StudyCatalog;
use LibreBooking\Studies\StudyDefinition;
use TestBase;

class StudyCatalogTest extends TestBase
{
    public function testFindsTheStudyOfAName()
    {
        $catalog = StudyCatalog::fromStudies([new StudyDefinition('^(?<study>ANT)_(?<subject>[0-9]{3})$', false)]);

        $this->assertTrue($catalog->isAvailable());
        $this->assertSame('ANT', $catalog->find('ANT_001')->prefix);
        $this->assertNull($catalog->find('XYZ_1'));
    }

    public function testUnavailableCatalogFindsNothing()
    {
        $catalog = StudyCatalog::unavailable();

        $this->assertFalse($catalog->isAvailable());
        $this->assertNull($catalog->find('ANT_001'));
    }

    public function testGitHubBlobPagesBecomeRawFiles()
    {
        $this->assertSame(
            'https://raw.githubusercontent.com/UNFmontreal/bids_configs/master/studies.json',
            StudyCatalog::rawUrl('https://github.com/UNFmontreal/bids_configs/blob/master/studies.json')
        );
    }
}
