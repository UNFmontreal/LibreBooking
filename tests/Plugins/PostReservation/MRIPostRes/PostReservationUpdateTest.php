<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/Notification/namespace.php');
require_once(ROOT_DIR . 'plugins/PostReservation/MRIPostRes/MRIPostRes.php');

use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\MriReservationContext;
use LibreBooking\Mri\ScreeningState;

class PostReservationUpdateTest extends TestBase
{
    private const LOGBOOK_SURVEY = 34;
    private const SCREENING_SURVEY = 12;
    private const REFERENCE = 'REF-1';

    /**
     * @var list<array{0: string, 1: array}>
     */
    private array $calls = [];

    public function setUp(): void
    {
        parent::setUp();

        $file = new FakeConfigFile();
        $file->SetKey(LimesurveyScreeningPreConfigKeys::LS_BASE_URL, 'https://ls');
        $file->SetKey(LimesurveyScreeningPreConfigKeys::SCREENING_SURVEY_ID, self::SCREENING_SURVEY);
        $file->SetKey(LimesurveyScreeningPreConfigKeys::LOGBOOK_SURVEY_ID, self::LOGBOOK_SURVEY);
        $file->SetKey(LimesurveyScreeningPreConfigKeys::LINK_TO_LOGBOOK, '[LS_BASEURL]/[LogbookSurveyID]?token=[TOKEN]');
        $file->SetKey(LimesurveyScreeningPreConfigKeys::MRI_OPERATORS, '{}');
        $file->SetKey(LimesurveyScreeningPreConfigKeys::RESOURCE_NAMES, 'IRM 3T');
        $this->fakeConfig->SetFile(LimesurveyScreeningPreConfigKeys::CONFIG_ID, $file);
    }

    public function testManualUpdateWithACompletedScreeningCreatesTheLogbookParticipantOfTheLink()
    {
        $this->update($this->context(completed: true))->Notify($this->series());

        $added = array_values(array_filter($this->calls, fn (array $call): bool => $call[0] === 'add_participants' && $call[1][1] === self::LOGBOOK_SURVEY));
        $this->assertCount(1, $added);
        $this->assertSame([['token' => self::REFERENCE]], $added[0][1][2]);
    }

    public function testCompletedScreeningLinkLeadsToTheScreeningAnswers()
    {
        $this->update($this->context(completed: true))->Notify($this->series());

        $this->assertContains(
            'https://ls/index.php/admin/dataentry/sa/editdata/subaction/edit/surveyid/' . self::SCREENING_SURVEY . '/id/11',
            $this->storedAttributeValues()
        );
    }

    public function testUpdateWithoutACompletedScreeningCreatesNoLogbookParticipant()
    {
        $this->update($this->context(completed: false))->Notify($this->series());

        $this->assertSame([], array_filter($this->calls, fn (array $call): bool => $call[0] === 'add_participants' && $call[1][1] === self::LOGBOOK_SURVEY));
    }

    /**
     * @return string[] the values written to custom attributes
     */
    private function storedAttributeValues(): array
    {
        $values = [];
        foreach ($this->db->GetCommandsOfType(AddAttributeValueCommand::class) as $command) {
            for ($i = 0; $i < $command->Parameters->Count(); $i++) {
                $parameter = $command->Parameters->Items($i);
                if ($parameter->Name == ParameterNames::ATTRIBUTE_VALUE) {
                    $values[] = $parameter->Value;
                }
            }
        }

        return $values;
    }

    private function update(MriReservationContext $context): PostReservationUpdate
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', function (string $method, array $params): mixed {
            $this->calls[] = [$method, $params];

            return match ($method) {
                'get_session_key' => 'KEY',
                'get_participant_properties' => ['token' => 'SCREEN-1', 'tid' => '5', 'completed' => 'Y'],
                'get_response_ids' => [11],
                'add_participants' => [['token' => self::REFERENCE]],
                default => null,
            };
        });

        MriReservationContext::attach($this->series(), $context);

        return new class ($this->createMock(IReservationNotificationService::class), $remote) extends PostReservationUpdate {
            public function __construct(IReservationNotificationService $base, private RemoteControl $remote)
            {
                parent::__construct($base);
            }

            protected function withLimesurvey(callable $work): void
            {
                $this->remote->withSession(fn (string $key) => $work($this->remote, $key));
            }
        };
    }

    private function context(bool $completed): MriReservationContext
    {
        return new MriReservationContext(new ScreeningState('SCREEN-1', $completed, false, false, false, 'ANT_001', null), false);
    }

    private ?object $series = null;

    /**
     * A monitored reservation series with one instance, as the notification service receives it.
     */
    private function series(): object
    {
        return $this->series ??= new class (self::REFERENCE) {
            public function __construct(private string $reference)
            {
            }

            public function SeriesId()
            {
                return 7;
            }

            public function Title()
            {
                return 'ANT_001';
            }

            public function Description()
            {
                return '';
            }

            public function GetAttributeValue($attributeId)
            {
                return null;
            }

            public function AllResources()
            {
                return [new class () {
                    public function GetName()
                    {
                        return 'IRM 3T';
                    }
                }];
            }

            public function BookedBy()
            {
                return new class () {
                    public $Timezone = 'America/Montreal';
                };
            }

            public function CurrentInstance()
            {
                return $this->Instances()[0];
            }

            public function Instances()
            {
                $reference = $this->reference;

                return [new class ($reference) {
                    public function __construct(private string $reference)
                    {
                    }

                    public function ReferenceNumber()
                    {
                        return $this->reference;
                    }

                    public function ReservationId()
                    {
                        return 3;
                    }

                    public function StartDate()
                    {
                        return Date::Create(2026, 11, 3, 14, 0, 0, 'UTC');
                    }

                    public function EndDate()
                    {
                        return Date::Create(2026, 11, 3, 15, 0, 0, 'UTC');
                    }
                }];
            }
        };
    }
}
