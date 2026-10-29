<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\MriTime;
use TestBase;

class MriTimeTest extends TestBase
{
    public function testDatabaseTimeIsConvertedFromUtcToTheSiteZone()
    {
        // 13:00 UTC is 09:00 in Montreal during daylight saving time
        $moment = MriTime::fromDatabase('2026-10-28 13:00:00');

        $this->assertSame('2026-10-28 09:00:00', $moment->format('Y-m-d H:i:s'));
        $this->assertSame('America/Montreal', $moment->getTimezone()->getName());
    }

    public function testDatabaseTimeCanUseAnotherZone()
    {
        $this->assertSame('2026-10-28 13:00', MriTime::fromDatabase('2026-10-28 13:00:00', 'UTC')->format('Y-m-d H:i'));
    }

    public function testMomentIsConvertedToRepositoryDateInUtc()
    {
        $date = MriTime::toDate(new \DateTimeImmutable('2026-10-28 09:00', new \DateTimeZone('America/Montreal')));

        $this->assertSame('2026-10-28 13:00:00', $date->ToDatabase());
    }
}
