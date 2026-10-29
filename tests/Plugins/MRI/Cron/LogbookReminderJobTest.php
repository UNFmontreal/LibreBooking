<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Cron;

use DateTimeImmutable;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\Cron\LogbookReminderJob;
use LibreBooking\Tests\Plugins\MRI\Fakes\FakeLimesurvey;
use LibreBooking\Tests\Plugins\MRI\Fakes\FixedReservationSource;
use LibreBooking\Tests\Plugins\MRI\Fakes\RecordingMailer;
use LibreBooking\Tests\Plugins\MRI\Fakes\TestSupport;
use TestBase;

class LogbookReminderJobTest extends TestBase
{
    private const NOW = '2026-10-27 08:00:00';

    public function testIncompleteLogbookEmailsTheOperator()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'completed' => 'N'],
        ]);
        $mailer = new RecordingMailer();

        $sent = $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Created)], $mailer)
            ->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(1, $sent);
        $this->assertSame('operator@example.test', $mailer->sent[0]['to']);
        $this->assertSame('Logbook for ANT_001', $mailer->sent[0]['subject']);
        $this->assertStringContainsString('https://ls/logbook?token=OLD', $mailer->sent[0]['body']);
    }

    public function testCompletedLogbookWithExtraIncompleteResponseIsTidied()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'completed' => 'Y'],
            'get_response_ids' => fn (array $params) => [1, 2],
            'export_responses_by_token' => fn (array $params) => $params[5] === 'complete'
                ? TestSupport::base64Groups('{"responses":[[{"id":1}]]}')
                : TestSupport::base64Groups('{"responses":[[{"id":2}]]}'),
        ]);
        $mailer = new RecordingMailer();

        $sent = $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Created)], $mailer)
            ->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(0, $sent);
        $this->assertSame([], $mailer->sent);
        $deleted = array_values(array_filter($fake->calls, fn (array $call): bool => $call[0] === 'delete_response'));
        $this->assertCount(1, $deleted);
        $this->assertSame(2, $deleted[0][1][2]);
    }

    public function testCompletedLogbookWithSingleResponseIsLeftAlone()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'completed' => 'Y'],
            'get_response_ids' => fn (array $params) => [1],
        ]);

        $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Created)], new RecordingMailer())
            ->run(new DateTimeImmutable(self::NOW));

        $this->assertNotContains('delete_response', $fake->methods());
    }

    /**
     * @param \LibreBooking\Mri\MriReservation[] $finished
     */
    private function job(FakeLimesurvey $fake, array $finished, RecordingMailer $mailer): LogbookReminderJob
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', $fake->transport());

        return new LogbookReminderJob(
            $remote,
            new FixedReservationSource($finished),
            $mailer,
            TestSupport::settings(),
        );
    }
}
