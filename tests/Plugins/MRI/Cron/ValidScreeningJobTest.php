<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Cron;

use DateTimeImmutable;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\Cron\ValidScreeningJob;
use LibreBooking\Mri\ScreeningLookup;
use LibreBooking\Tests\Plugins\MRI\Fakes\FakeLimesurvey;
use LibreBooking\Tests\Plugins\MRI\Fakes\FixedReservationSource;
use LibreBooking\Tests\Plugins\MRI\Fakes\RecordingAttributeStore;
use LibreBooking\Tests\Plugins\MRI\Fakes\TestSupport;
use TestBase;

class ValidScreeningJobTest extends TestBase
{
    private const NOW = '2026-10-27 08:00:00';

    public function testCompletedScreeningMarksTheReservationCreated()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => isset($params[2]['token'])
                ? ['status' => 'Error: No results were found based on your attributes.', 'error_code' => 'ERR_NOT_FOUND']
                : ['token' => 'REF-A', 'tid' => '4', 'completed' => 'Y'],
            'get_response_ids' => fn (array $params) => [5],
            'export_responses_by_token' => fn (array $params) => $params[5] === 'complete'
                ? TestSupport::base64Groups('{"responses":[[{"id":5}]]}')
                : TestSupport::base64Groups('{"responses":[]}'),
        ]);
        $reservations = new FixedReservationSource([TestSupport::reservation('HOF_001_pre1', 'REF-2', \ReservationStatus::Pending)]);
        $attributes = new RecordingAttributeStore();
        $attributes->values['100:8'] = 'Screening Not complete';

        $completed = $this->job($fake, $reservations, $attributes)->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(1, $completed);
        $this->assertSame(\ReservationStatus::Created, $reservations->statusWrites[100]);
        $this->assertSame('https://ls/logbook?token=REF-A&id=5', $attributes->values['100:9']);
        $this->assertSame('Screening Complete', $attributes->values['100:8']);
        $this->assertContains('add_participants', $fake->methods());
        $this->assertContains('release_session_key', $fake->methods());
    }

    public function testIncompleteScreeningIsLeftPending()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'tid' => '4', 'completed' => 'N'],
        ]);
        $reservations = new FixedReservationSource([TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending)]);
        $attributes = new RecordingAttributeStore();

        $completed = $this->job($fake, $reservations, $attributes)->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(0, $completed);
        $this->assertSame([], $reservations->statusWrites);
        $this->assertNotContains('add_participants', $fake->methods());
    }

    public function testReservationsWithoutAStudyAreSkipped()
    {
        $fake = FakeLimesurvey::withDefaults();
        $reservations = new FixedReservationSource([TestSupport::reservation('NOPE', 'REF-1', \ReservationStatus::Pending)]);

        $completed = $this->job($fake, $reservations, new RecordingAttributeStore())->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(0, $completed);
        $this->assertSame([], $reservations->statusWrites);
    }

    public function testPhantomIsNotCompletedByTheScreeningCron()
    {
        $fake = FakeLimesurvey::withDefaults();
        $reservations = new FixedReservationSource([TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending, true)]);

        $completed = $this->job($fake, $reservations, new RecordingAttributeStore())->run(new DateTimeImmutable(self::NOW));

        $this->assertSame(0, $completed);
    }

    public function testNothingIsDoneWithoutPendingReservations()
    {
        $fake = FakeLimesurvey::withDefaults();

        $this->job($fake, new FixedReservationSource([]), new RecordingAttributeStore())->run(new DateTimeImmutable(self::NOW));

        $this->assertSame([], $fake->calls);
    }

    private function job(FakeLimesurvey $fake, FixedReservationSource $reservations, RecordingAttributeStore $attributes): ValidScreeningJob
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', $fake->transport());

        return new ValidScreeningJob(
            $remote,
            new ScreeningLookup($remote, TestSupport::SCREENING_SURVEY, TestSupport::catalog()),
            $reservations,
            $attributes,
            TestSupport::settings(),
        );
    }
}
