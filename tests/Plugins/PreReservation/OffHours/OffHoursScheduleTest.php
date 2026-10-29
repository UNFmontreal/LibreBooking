<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'plugins/PreReservation/OffHours/OffHoursSchedule.php');

class OffHoursScheduleTest extends TestBase
{
    // 2026-10-26 is a Monday, 2026-10-27 a Tuesday, 2026-10-31 a Saturday and 2026-11-01 a Sunday

    public function testIntervalInsideRegularHoursIsContained()
    {
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-18:00');

        $this->assertTrue($schedule->Contains($this->date('2026-10-26 09:00'), $this->date('2026-10-26 10:00'), 'UTC'));
    }

    public function testIntervalTouchingBoundariesIsContained()
    {
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-18:00');

        $this->assertTrue($schedule->Contains($this->date('2026-10-26 08:00'), $this->date('2026-10-26 18:00'), 'UTC'));
    }

    public function testStartingBeforeRegularHoursIsNotContained()
    {
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-18:00');

        $this->assertFalse($schedule->Contains($this->date('2026-10-26 07:59'), $this->date('2026-10-26 09:00'), 'UTC'));
    }

    public function testEndingAfterRegularHoursIsNotContained()
    {
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-18:00');

        $this->assertFalse($schedule->Contains($this->date('2026-10-26 17:00'), $this->date('2026-10-26 18:01'), 'UTC'));
    }

    public function testWeekendIsNotContainedByWeekdayRange()
    {
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-18:00');

        $this->assertFalse($schedule->Contains($this->date('2026-10-31 09:00'), $this->date('2026-10-31 10:00'), 'UTC'));
        $this->assertFalse($schedule->Contains($this->date('2026-11-01 09:00'), $this->date('2026-11-01 10:00'), 'UTC'));
    }

    public function testMultipleEntriesApplyToTheirOwnDays()
    {
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-18:00;Sat:09:00-12:00');

        $this->assertTrue($schedule->Contains($this->date('2026-10-31 10:00'), $this->date('2026-10-31 11:00'), 'UTC'));
        $this->assertFalse($schedule->Contains($this->date('2026-10-31 13:00'), $this->date('2026-10-31 14:00'), 'UTC'));
    }

    public function testCommaSeparatedDaysAreParsed()
    {
        $schedule = new OffHoursSchedule('Mon,Wed:08:00-18:00');

        $this->assertTrue($schedule->Contains($this->date('2026-10-26 09:00'), $this->date('2026-10-26 10:00'), 'UTC'));
        $this->assertFalse($schedule->Contains($this->date('2026-10-27 09:00'), $this->date('2026-10-27 10:00'), 'UTC'));
        $this->assertTrue($schedule->Contains($this->date('2026-10-28 09:00'), $this->date('2026-10-28 10:00'), 'UTC'));
    }

    public function testWrappingDayRangeIsParsed()
    {
        $schedule = new OffHoursSchedule('Fri-Mon:00:00-24:00');

        $this->assertTrue($schedule->Contains($this->date('2026-11-01 23:00'), $this->date('2026-11-01 23:30'), 'UTC'));
        $this->assertFalse($schedule->Contains($this->date('2026-10-27 09:00'), $this->date('2026-10-27 10:00'), 'UTC'));
    }

    public function testIntervalCrossingMidnightIsNotContained()
    {
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-24:00');

        $this->assertFalse($schedule->Contains($this->date('2026-10-26 22:00'), $this->date('2026-10-27 01:00'), 'UTC'));
    }

    public function testIntervalEndingAtNextMidnightIsContainedWhenDayIsOpenUntilMidnight()
    {
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-24:00');

        $this->assertTrue($schedule->Contains($this->date('2026-10-26 22:00'), $this->date('2026-10-27 00:00'), 'UTC'));
    }

    public function testTimesAreEvaluatedInScheduleTimezone()
    {
        // 21:00 UTC is 17:00 in New York during daylight saving time
        $schedule = new OffHoursSchedule('Mon-Fri:08:00-18:00');

        $this->assertTrue($schedule->Contains($this->date('2026-10-26 21:00'), $this->date('2026-10-26 22:00'), 'America/New_York'));
        $this->assertFalse($schedule->Contains($this->date('2026-10-26 21:00'), $this->date('2026-10-26 22:00'), 'UTC'));
    }

    public function testInvalidEntriesAreIgnored()
    {
        $schedule = new OffHoursSchedule('Foo:08:00-18:00;Mon-Fri:18:00-08:00;Mon-Fri:08:00-18:00');

        $this->assertTrue($schedule->Contains($this->date('2026-10-26 09:00'), $this->date('2026-10-26 10:00'), 'UTC'));
        $this->assertFalse($schedule->Contains($this->date('2026-10-26 19:00'), $this->date('2026-10-26 20:00'), 'UTC'));
    }

    private function date(string $value): Date
    {
        return new Date($value . ':00', 'UTC');
    }
}
