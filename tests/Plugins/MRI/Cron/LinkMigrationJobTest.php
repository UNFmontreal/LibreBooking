<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Cron;

use DateTimeImmutable;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\Cron\LinkMigrationJob;
use LibreBooking\Mri\ScreeningLookup;
use LibreBooking\Tests\Plugins\MRI\Fakes\FakeLimesurvey;
use LibreBooking\Tests\Plugins\MRI\Fakes\FixedReservationSource;
use LibreBooking\Tests\Plugins\MRI\Fakes\RecordingAttributeStore;
use LibreBooking\Tests\Plugins\MRI\Fakes\TestSupport;
use TestBase;

class LinkMigrationJobTest extends TestBase
{
    private const NOW = '2026-10-27 08:00:00';
    private const NOT_FOUND = ['status' => 'Error: No results were found based on your attributes.', 'error_code' => 'ERR_NOT_FOUND'];

    public function testCompletedScreeningGetsAnswersAndLogbookLinks()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'tid' => '4', 'completed' => 'Y'],
            'get_response_ids' => fn (array $params) => [5],
            'export_responses_by_token' => fn (array $params) => TestSupport::base64Groups('{"responses":[[{"id":5}]]}'),
        ]);
        $attributes = new RecordingAttributeStore();
        $attributes->values['100:8'] = '<a href="https://old">Screening</a>';

        $report = $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Created)], $attributes, true);

        $this->assertSame(LinkMigrationJob::UPDATED, $report[0]['outcome']);
        $this->assertSame('https://ls/index.php/admin/dataentry/sa/editdata/subaction/edit/surveyid/12/id/5', $attributes->values['100:8']);
        $this->assertSame('https://ls/logbook?token=REF-1&id=5', $attributes->values['100:9']);
    }

    public function testScreeningToDoGetsTheTokenPageAndNoLogbookLink()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'tid' => '4', 'completed' => 'N'],
        ]);
        $attributes = new RecordingAttributeStore();

        $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending)], $attributes, true);

        $this->assertSame('https://ls/12/token/REF-1', $attributes->values['100:8']);
        $this->assertArrayNotHasKey('100:9', $attributes->values);
    }

    public function testPhantomNeedsNoParticipant()
    {
        $fake = FakeLimesurvey::withDefaults();
        $attributes = new RecordingAttributeStore();

        $report = $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Created, true)], $attributes, true);

        $this->assertSame(LinkMigrationJob::UPDATED, $report[0]['outcome']);
        $this->assertSame('Screening Complete', $attributes->values['100:8']);
        $this->assertSame('https://ls/logbook?token=REF-1&id=0', $attributes->values['100:9']);
        $this->assertNotContains('get_participant_properties', $fake->methods());
    }

    public function testMissingParticipantIsReportedAndLeftUnchanged()
    {
        $fake = FakeLimesurvey::withDefaults(['get_participant_properties' => fn (array $params) => self::NOT_FOUND]);
        $attributes = new RecordingAttributeStore();
        $attributes->values['100:8'] = 'old';

        $report = $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending)], $attributes, true);

        $this->assertSame(LinkMigrationJob::MISSING_PARTICIPANT, $report[0]['outcome']);
        $this->assertSame('old', $attributes->values['100:8']);
    }

    public function testUnknownStudyIsReported()
    {
        $report = $this->job(FakeLimesurvey::withDefaults(), [TestSupport::reservation('NOPE', 'REF-1', \ReservationStatus::Pending)], new RecordingAttributeStore(), true);

        $this->assertSame(LinkMigrationJob::NO_STUDY, $report[0]['outcome']);
    }

    public function testDryRunWritesNothing()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'tid' => '4', 'completed' => 'N'],
        ]);
        $attributes = new RecordingAttributeStore();

        $report = $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending)], $attributes, false);

        $this->assertSame(LinkMigrationJob::UPDATED, $report[0]['outcome']);
        $this->assertSame([], $attributes->values);
    }

    public function testCurrentLinksAreUnchanged()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-1', 'tid' => '4', 'completed' => 'N'],
        ]);
        $attributes = new RecordingAttributeStore();
        $attributes->values['100:8'] = 'https://ls/12/token/REF-1';

        $report = $this->job($fake, [TestSupport::reservation('ANT_001', 'REF-1', \ReservationStatus::Pending)], $attributes, true);

        $this->assertSame(LinkMigrationJob::UNCHANGED, $report[0]['outcome']);
    }

    /**
     * @param \LibreBooking\Mri\MriReservation[] $reservations
     * @return list<array{reference: string, session: string, outcome: string, detail: string}>
     */
    private function job(FakeLimesurvey $fake, array $reservations, RecordingAttributeStore $attributes, bool $apply): array
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', $fake->transport());

        return (new LinkMigrationJob(
            $remote,
            new ScreeningLookup($remote, TestSupport::SCREENING_SURVEY, TestSupport::catalog()),
            new FixedReservationSource($reservations),
            $attributes,
            TestSupport::settings(),
        ))->run(new DateTimeImmutable(self::NOW), $apply);
    }
}
