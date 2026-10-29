<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Cron;

use DateTimeImmutable;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\Cron\ScreeningReminderJob;
use LibreBooking\Mri\ScreeningLookup;
use LibreBooking\Tests\Plugins\MRI\Fakes\FakeLimesurvey;
use LibreBooking\Tests\Plugins\MRI\Fakes\FixedReservationSource;
use LibreBooking\Tests\Plugins\MRI\Fakes\RecordingMailer;
use LibreBooking\Tests\Plugins\MRI\Fakes\TestSupport;
use TestBase;

class ScreeningReminderJobTest extends TestBase
{
    private const NOW = '2026-10-27 08:00:00';

    public function testUncompletedScreeningEmailsTheOwner()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'tid' => '4', 'completed' => 'N'],
        ]);
        $mailer = new RecordingMailer();

        $sent = $this->job($fake, new FixedReservationSource([TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending)]), $mailer)
            ->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(1, $sent);
        $this->assertSame('owner@example.test', $mailer->sent[0]['to']);
        $this->assertSame('Screening for ANT_001', $mailer->sent[0]['subject']);
        $this->assertStringContainsString('Hi Jane, not yet screened: https://ls/12/token/REF-1', $mailer->sent[0]['body']);
    }

    public function testCompletedScreeningIsNotReminded()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'tid' => '4', 'completed' => 'Y'],
        ]);
        $mailer = new RecordingMailer();

        $sent = $this->job($fake, new FixedReservationSource([TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending)]), $mailer)
            ->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(0, $sent);
        $this->assertSame([], $mailer->sent);
    }

    public function testPhantomIsNotReminded()
    {
        $fake = FakeLimesurvey::withDefaults();
        $mailer = new RecordingMailer();

        $sent = $this->job($fake, new FixedReservationSource([TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending, true)]), $mailer)
            ->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(0, $sent);
    }

    public function testLaterMultiSessionVisitIsNotReminded()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => function (array $params): array {
                return isset($params[2]['firstname'])
                    ? ['token' => 'REF-1', 'tid' => '3', 'completed' => 'N']
                    : ['status' => 'Error: No results were found based on your attributes.', 'error_code' => 'ERR_NOT_FOUND'];
            },
        ]);
        $mailer = new RecordingMailer();

        $sent = $this->job($fake, new FixedReservationSource([TestSupport::reservation('HOF_001_pre2', 'REF-3', \ReservationStatus::Pending)]), $mailer)
            ->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(0, $sent);
    }

    private function job(FakeLimesurvey $fake, FixedReservationSource $reservations, RecordingMailer $mailer): ScreeningReminderJob
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', $fake->transport());

        return new ScreeningReminderJob(
            $remote,
            new ScreeningLookup($remote, TestSupport::SCREENING_SURVEY, TestSupport::catalog()),
            $reservations,
            $mailer,
            TestSupport::settings(),
        );
    }
}
