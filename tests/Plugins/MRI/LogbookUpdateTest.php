<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use DateTimeImmutable;
use LibreBooking\Mri\LogbookUpdate;
use TestBase;

class LogbookUpdateTest extends TestBase
{
    private const NOW = '2026-10-29 12:00';

    public function testReservationStartingMoreThanSevenDaysAheadIsNotLogged()
    {
        $update = $this->detect('2026-11-20 10:00', '2026-11-20 12:00', '2026-11-21 10:00', '2026-11-21 12:00');

        $this->assertNull($update);
    }

    public function testReservationMovedToALaterDateLogsThePreviousReservation()
    {
        $update = $this->detect('2026-11-01 10:00', '2026-11-01 12:00', '2026-11-02 10:00', '2026-11-02 12:00');

        $this->assertSame('2026-11-01 12:00', $update->endTime);
        $this->assertSame('', $update->commentSuffix);
    }

    public function testReservationMovedEarlierAndShortenedLogsTheCutTime()
    {
        $update = $this->detect('2026-11-02 10:00', '2026-11-02 12:00', '2026-11-01 10:00', '2026-11-01 11:00');

        $this->assertSame('2026-11-02 11:00', $update->endTime);
        $this->assertSame(
            ' Reservation updated to an earlier date. Before: 2026-11-02 10:00 to 2026-11-02 12:00 After: 2026-11-01 10:00 to 2026-11-01 11:00',
            $update->commentSuffix
        );
    }

    public function testReservationShortenedInPlaceLogsTheCutTime()
    {
        $update = $this->detect('2026-11-01 10:00', '2026-11-01 12:00', '2026-11-01 10:00', '2026-11-01 11:00');

        $this->assertSame('2026-11-01 11:00', $update->endTime);
        $this->assertStringContainsString('Reservation updated with less time.', $update->commentSuffix);
    }

    public function testReservationMovedEarlierWithoutLosingTimeIsNotLogged()
    {
        $update = $this->detect('2026-11-02 10:00', '2026-11-02 12:00', '2026-11-01 10:00', '2026-11-01 12:00');

        $this->assertNull($update);
    }

    public function testReservationExtendedInPlaceIsNotLogged()
    {
        $update = $this->detect('2026-11-01 10:00', '2026-11-01 12:00', '2026-11-01 10:00', '2026-11-01 13:00');

        $this->assertNull($update);
    }

    private function detect(string $previousStart, string $previousEnd, string $currentStart, string $currentEnd): ?LogbookUpdate
    {
        return LogbookUpdate::detect(
            new DateTimeImmutable($previousStart),
            new DateTimeImmutable($previousEnd),
            new DateTimeImmutable($currentStart),
            new DateTimeImmutable($currentEnd),
            new DateTimeImmutable(self::NOW)
        );
    }
}
