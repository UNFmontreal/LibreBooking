<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'plugins/PostReservation/MRIPostRes/MriCancellationWindow.php');

class MriCancellationWindowTest extends TestBase
{
    private DateTimeZone $tz;

    public function setUp(): void
    {
        parent::setUp();
        $this->tz = new DateTimeZone('America/Montreal');
    }

    public function testStartInsideWindowIsLate()
    {
        $now = new DateTimeImmutable('2026-10-28 15:00', $this->tz);
        $start = new DateTimeImmutable('2026-11-02 09:00', $this->tz);

        $this->assertTrue(MriCancellationWindow::startsWithin($start, 7, $now));
    }

    public function testStartAfterWindowIsNotLate()
    {
        $now = new DateTimeImmutable('2026-10-28 15:00', $this->tz);
        $start = new DateTimeImmutable('2026-11-10 09:00', $this->tz);

        $this->assertFalse(MriCancellationWindow::startsWithin($start, 7, $now));
    }

    public function testWindowEndsAtMidnightOfTheLastDay()
    {
        $now = new DateTimeImmutable('2026-10-28 15:00', $this->tz);

        // the limit is midnight at the start of 2026-11-04 (today + 7 days)
        $this->assertTrue(MriCancellationWindow::startsWithin(new DateTimeImmutable('2026-11-03 23:59', $this->tz), 7, $now));
        $this->assertFalse(MriCancellationWindow::startsWithin(new DateTimeImmutable('2026-11-04 00:00', $this->tz), 7, $now));
    }

    public function testReservationStartedInThePastIsLate()
    {
        $now = new DateTimeImmutable('2026-10-28 15:00', $this->tz);

        $this->assertTrue(MriCancellationWindow::startsWithin(new DateTimeImmutable('2026-10-27 09:00', $this->tz), 7, $now));
    }
}
