<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use DateTimeImmutable;
use DateTimeZone;
use LibreBooking\Mri\WorkingHours;
use TestBase;

class WorkingHoursTest extends TestBase
{
    // 2026-10-26 is a Monday (ISO day 1)
    private const HOURS = [
        1 => ['08:00', '12:00', '13:00', '17:00'],
        5 => ['08:00', '17:00'],
        7 => ['00:00', '24:00'],
    ];

    public function testReservationInsideTheMorningIsPermitted()
    {
        $this->assertTrue(WorkingHours::permits($this->at('2026-10-26 09:00'), $this->at('2026-10-26 10:00'), self::HOURS));
    }

    public function testReservationInsideTheAfternoonIsPermitted()
    {
        $this->assertTrue(WorkingHours::permits($this->at('2026-10-26 14:00'), $this->at('2026-10-26 16:00'), self::HOURS));
    }

    public function testReservationAcrossTheLunchBreakIsNotPermitted()
    {
        $this->assertFalse(WorkingHours::permits($this->at('2026-10-26 11:00'), $this->at('2026-10-26 14:00'), self::HOURS));
    }

    public function testReservationBeforeOpeningIsNotPermitted()
    {
        $this->assertFalse(WorkingHours::permits($this->at('2026-10-26 07:00'), $this->at('2026-10-26 08:30'), self::HOURS));
    }

    public function testDayWithoutHoursIsNotPermitted()
    {
        // Tuesday has no hours configured
        $this->assertFalse(WorkingHours::permits($this->at('2026-10-27 09:00'), $this->at('2026-10-27 10:00'), self::HOURS));
    }

    public function testReservationSpanningTwoDaysIsNotPermitted()
    {
        $this->assertFalse(WorkingHours::permits($this->at('2026-10-30 16:00'), $this->at('2026-10-31 09:00'), self::HOURS));
    }

    public function testReservationStartingAtTheDayCountsAsWholeDay()
    {
        // Sunday's hours cover the whole day
        $this->assertTrue(WorkingHours::permits($this->at('2026-11-01 22:00'), $this->at('2026-11-01 23:59'), self::HOURS));
        $this->assertFalse(WorkingHours::permits($this->at('2026-11-01 22:00'), $this->at('2026-11-02 00:00'), self::HOURS));
    }

    public function testWindowsCanBeGivenWithoutASecondWindow()
    {
        // Friday's single window: 08:00-17:00
        $this->assertTrue(WorkingHours::permits($this->at('2026-10-30 08:00'), $this->at('2026-10-30 17:00'), self::HOURS));
        $this->assertFalse(WorkingHours::permits($this->at('2026-10-30 17:00'), $this->at('2026-10-30 18:00'), self::HOURS));
    }

    public function testAnyInstanceInsideHoursIsEnough()
    {
        $instances = [
            $this->instance('2026-10-26 07:00', '2026-10-26 08:30'),
            $this->instance('2026-10-26 09:00', '2026-10-26 10:00'),
        ];

        $this->assertTrue(WorkingHours::anyInstancePermitted($instances, 'America/Montreal', self::HOURS));
        $this->assertFalse(WorkingHours::anyInstancePermitted([$instances[0]], 'America/Montreal', self::HOURS));
    }

    private function at(string $local): DateTimeImmutable
    {
        return new DateTimeImmutable($local, new DateTimeZone('America/Montreal'));
    }

    private function instance(string $start, string $end): object
    {
        $startDate = \Date::Parse($start, 'America/Montreal');
        $endDate = \Date::Parse($end, 'America/Montreal');

        return new class ($startDate, $endDate) {
            public function __construct(private \Date $start, private \Date $end)
            {
            }

            public function StartDate()
            {
                return $this->start;
            }

            public function EndDate()
            {
                return $this->end;
            }
        };
    }
}
