<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/Validation/namespace.php');
require_once(ROOT_DIR . 'plugins/PreReservation/LimesurveyScreeningPre/LimesurveyScreeningPre.php');

use LibreBooking\Studies\StudyCatalog;

class LimesurveyScreeningValidationTest extends TestBase
{
    private const URL = 'https://example.test/studies.json';
    private const JSON = '{"ant": {"regex": "^(ANT)_([0-9]{3})$", "multisession": 0}, "hof": {"regex": "^(?<study>HOF)_(?<subject>[0-9]{3})_(?<session>pre[0-9])$", "multisession": 1}}';
    private const RESOURCE = 10;
    private const OTHER_RESOURCE = 99;
    private const SESSION_ATTRIBUTE = 5;
    private const PHANTOM_ATTRIBUTE = 6;
    private const UNF_ATTRIBUTE = 8;

    private const MSG_REQUIRED = 'required';
    private const MSG_PHANTOM = 'phantom';
    private const MSG_RECURRING = 'recurring';
    private const MSG_DUPLICATE = 'duplicate';
    private const MSG_RESOURCE = 'resource';
    private const MSG_UNAVAILABLE = 'unavailable';
    private const MSG_INVALID_SESSION = 'invalid session name';

    private string $cacheFile;

    public function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = sys_get_temp_dir() . '/limesurvey-screening-test-' . uniqid() . '.json';
    }

    public function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function testBaseFailureIsReturnedWithoutScreening()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->expects($this->never())->method('participantExists');
        $base = $this->baseReturning(new ReservationValidationResult(false, ['base failure']));

        $result = $this->validation($base, false, $gateway)->Validate($this->series([self::RESOURCE], 'ANT_001'));

        $this->assertFalse($result->CanBeSaved());
        $this->assertSame(['base failure'], $result->GetErrors());
    }

    public function testReservationOfUnmonitoredResourceIsNotScreened()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->expects($this->never())->method('participantExists');

        $result = $this->validation($this->validBase(), false, $gateway)
            ->Validate($this->series([self::OTHER_RESOURCE], ''));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testMissingSessionNameIsDenied()
    {
        $result = $this->validation($this->validBase(), false, $this->gatewayReturning(false))
            ->Validate($this->series([self::RESOURCE], '   '));

        $this->assertFalse($result->CanBeSaved());
        $this->assertSame([self::MSG_REQUIRED], $result->GetErrors());
    }

    public function testSessionNameOfNoStudyIsDenied()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->expects($this->never())->method('participantExists');

        $result = $this->validation($this->validBase(), false, $gateway)
            ->Validate($this->series([self::RESOURCE], 'NOPE_1'));

        $this->assertSame([self::MSG_INVALID_SESSION], $result->GetErrors());
    }

    public function testSessionNameOfNoStudyIsDeniedOnUpdateToo()
    {
        $reservations = $this->reservationsReturning([self::RESOURCE], [self::SESSION_ATTRIBUTE => 'ANT_001']);

        $result = $this->validation($this->validBase(), true, $this->gatewayReturning(false), null, $reservations)
            ->Validate($this->series([self::RESOURCE], 'NOPE_1', referenceNumber: 'REF-1'));

        $this->assertSame([self::MSG_INVALID_SESSION], $result->GetErrors());
    }

    public function testUnavailableStudiesAreDenied()
    {
        $validation = $this->validation($this->validBase(), false, $this->gatewayReturning(false), fn (string $url): ?string => null);

        $result = $validation->Validate($this->series([self::RESOURCE], 'ANT_001'));

        $this->assertFalse($result->CanBeSaved());
        $this->assertSame([self::MSG_UNAVAILABLE], $result->GetErrors());
    }

    public function testPhantomMultiSessionIsDenied()
    {
        $result = $this->validation($this->validBase(), false, $this->gatewayReturning(false))
            ->Validate($this->series([self::RESOURCE], 'HOF_001_pre1', phantom: true));

        $this->assertSame([self::MSG_PHANTOM], $result->GetErrors());
    }

    public function testRecurringSingleSessionIsDenied()
    {
        $result = $this->validation($this->validBase(), false, $this->gatewayReturning(false))
            ->Validate($this->series([self::RESOURCE], 'ANT_001', recurring: true));

        $this->assertSame([self::MSG_RECURRING], $result->GetErrors());
    }

    public function testRecurringMultiSessionIsAllowed()
    {
        $result = $this->validation($this->validBase(), false, $this->gatewayReturning(false))
            ->Validate($this->series([self::RESOURCE], 'HOF_001_pre1', recurring: true));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testExistingSingleSessionParticipantIsDenied()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->expects($this->once())
            ->method('participantExists')
            ->with('ANT_001')
            ->willReturn(true);

        $result = $this->validation($this->validBase(), false, $gateway)
            ->Validate($this->series([self::RESOURCE], 'ANT_001'));

        $this->assertSame([self::MSG_DUPLICATE], $result->GetErrors());
    }

    public function testNewSingleSessionParticipantIsAllowed()
    {
        $result = $this->validation($this->validBase(), false, $this->gatewayReturning(false))
            ->Validate($this->series([self::RESOURCE], 'ANT_001'));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testMultiSessionDoesNotCheckForDuplicateParticipant()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->expects($this->never())->method('participantExists');

        $result = $this->validation($this->validBase(), false, $gateway)
            ->Validate($this->series([self::RESOURCE], 'HOF_001_pre1'));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testMultiSessionVisitsUseTheSubjectAsParticipant()
    {
        $requests = [];
        $rules = $this->createMock(LimesurveyScreeningRules::class);
        $rules->method('violation')->willReturnCallback(function (LimesurveyScreeningRequest $request) use (&$requests): ?string {
            $requests[] = $request;
            return null;
        });
        $studies = fn (): StudyCatalog => StudyCatalog::fromDownload(self::URL, 3600, $this->cacheFile, fn (string $url): ?string => self::JSON);
        $settings = new LimesurveyScreeningSettings([self::RESOURCE], self::SESSION_ATTRIBUTE, self::PHANTOM_ATTRIBUTE, self::MSG_REQUIRED, self::MSG_INVALID_SESSION, self::MSG_UNAVAILABLE);
        $validation = new LimesurveyScreeningValidation($this->validBase(), $settings, false, $studies, $rules, $this->createMock(IReservationRepository::class));

        $validation->Validate($this->series([self::RESOURCE], 'HOF_001_pre1'));
        $validation->Validate($this->series([self::RESOURCE], 'HOF_001_pre2'));

        $this->assertSame('HOF_001', $requests[0]->participantName);
        $this->assertSame('HOF_001', $requests[1]->participantName);
    }

    public function testDuplicateCheckIsSkippedOnUpdate()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->expects($this->never())->method('participantExists');
        $reservations = $this->reservationsReturning([self::RESOURCE]);

        $result = $this->validation($this->validBase(), true, $gateway, null, $reservations)
            ->Validate($this->series([self::RESOURCE], 'ANT_001', referenceNumber: 'REF-1'));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testChangingTheResourceOfAnUpdateIsDenied()
    {
        $reservations = $this->reservationsReturning([self::RESOURCE]);

        $result = $this->validation($this->validBase(), true, $this->gatewayReturning(false), null, $reservations)
            ->Validate($this->series([self::RESOURCE, self::OTHER_RESOURCE], 'HOF_001_pre1', referenceNumber: 'REF-1'));

        $this->assertSame([self::MSG_RESOURCE], $result->GetErrors());
    }

    public function testLimesurveyErrorDeniesWithUnavailableMessage()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->method('participantExists')->willThrowException(new RuntimeException('down'));

        $result = $this->validation($this->validBase(), false, $gateway)
            ->Validate($this->series([self::RESOURCE], 'ANT_001'));

        $this->assertFalse($result->CanBeSaved());
        $this->assertSame([self::MSG_UNAVAILABLE], $result->GetErrors());
    }

    public function testRenamingAnUpdateToAnExistingParticipantIsDenied()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->expects($this->once())
            ->method('participantExists')
            ->with('ANT_002')
            ->willReturn(true);
        $reservations = $this->reservationsReturning([self::RESOURCE], [self::SESSION_ATTRIBUTE => 'ANT_001']);

        $result = $this->validation($this->validBase(), true, $gateway, null, $reservations)
            ->Validate($this->series([self::RESOURCE], 'ANT_002', referenceNumber: 'REF-1'));

        $this->assertSame([self::MSG_DUPLICATE], $result->GetErrors());
    }

    public function testRenamingAnUpdateToANewParticipantIsAllowed()
    {
        $reservations = $this->reservationsReturning([self::RESOURCE], [self::SESSION_ATTRIBUTE => 'ANT_001']);

        $result = $this->validation($this->validBase(), true, $this->gatewayReturning(false), null, $reservations)
            ->Validate($this->series([self::RESOURCE], 'ANT_002', referenceNumber: 'REF-1'));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testPhantomBecomingRegularChecksForAnExistingParticipant()
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->expects($this->once())
            ->method('participantExists')
            ->with('ANT_001')
            ->willReturn(true);
        $reservations = $this->reservationsReturning([self::RESOURCE], [self::SESSION_ATTRIBUTE => 'ANT_001', self::PHANTOM_ATTRIBUTE => '1']);

        $result = $this->validation($this->validBase(), true, $gateway, null, $reservations)
            ->Validate($this->series([self::RESOURCE], 'ANT_001', referenceNumber: 'REF-1'));

        $this->assertSame([self::MSG_DUPLICATE], $result->GetErrors());
    }

    public function testTechnicalIssueIsAllowed()
    {
        $reservations = $this->reservationsReturning([self::RESOURCE], [self::SESSION_ATTRIBUTE => 'ANT_001']);

        $result = $this->validation($this->validBase(), true, $this->gatewayReturning(false), null, $reservations)
            ->Validate($this->series([self::RESOURCE], 'ANT_001', referenceNumber: 'REF-1', technicalIssue: true));

        $this->assertTrue($result->CanBeSaved());
    }

    private function validBase(): IReservationValidationService
    {
        return $this->baseReturning(new ReservationValidationResult());
    }

    private function baseReturning(ReservationValidationResult $result): IReservationValidationService
    {
        $base = $this->createMock(IReservationValidationService::class);
        $base->method('Validate')->willReturn($result);
        return $base;
    }

    private function gatewayReturning(bool $exists): LimesurveyParticipantGateway
    {
        $gateway = $this->createMock(LimesurveyParticipantGateway::class);
        $gateway->method('participantExists')->willReturn($exists);
        return $gateway;
    }

    /**
     * @param int[] $storedResourceIds
     * @param array<int, string> $storedAttributes attribute values of the stored reservation by attribute id
     */
    private function reservationsReturning(array $storedResourceIds, array $storedAttributes = []): IReservationRepository
    {
        $stored = new class ($storedResourceIds, $storedAttributes) {
            public function __construct(private array $ids, private array $attributes)
            {
            }

            public function AllResourceIds()
            {
                return $this->ids;
            }

            public function GetAttributeValue($customAttributeId)
            {
                return $this->attributes[$customAttributeId] ?? null;
            }
        };

        $repository = $this->createMock(IReservationRepository::class);
        $repository->method('LoadByReferenceNumber')->willReturn($stored);
        return $repository;
    }

    private function validation(
        IReservationValidationService $base,
        bool $isUpdate,
        LimesurveyParticipantGateway $gateway,
        ?callable $fetcher = null,
        ?IReservationRepository $reservations = null
    ): LimesurveyScreeningValidation {
        $studies = fn (): StudyCatalog => StudyCatalog::fromDownload(self::URL, 3600, $this->cacheFile, $fetcher ?? fn (string $url): ?string => self::JSON);

        $settings = new LimesurveyScreeningSettings(
            monitoredResourceIds: [self::RESOURCE],
            sessionNameAttributeId: self::SESSION_ATTRIBUTE,
            phantomAttributeId: self::PHANTOM_ATTRIBUTE,
            sessionNameRequiredMessage: self::MSG_REQUIRED,
            invalidSessionNameMessage: self::MSG_INVALID_SESSION,
            unavailableMessage: self::MSG_UNAVAILABLE,
            unfTechnicalIssueAttributeId: self::UNF_ATTRIBUTE,
        );

        $rules = new LimesurveyScreeningRules($gateway, self::MSG_PHANTOM, self::MSG_RECURRING, self::MSG_DUPLICATE, self::MSG_RESOURCE);

        return new LimesurveyScreeningValidation(
            $base,
            $settings,
            $isUpdate,
            $studies,
            $rules,
            $reservations ?? $this->createMock(IReservationRepository::class)
        );
    }

    /**
     * @param int[] $resourceIds
     */
    private function series(
        array $resourceIds,
        string $sessionName,
        bool $phantom = false,
        bool $recurring = false,
        string $referenceNumber = 'REF-NEW',
        bool $technicalIssue = false
    ): object {
        $attributes = [
            self::SESSION_ATTRIBUTE => $sessionName,
            self::PHANTOM_ATTRIBUTE => $phantom ? '1' : '0',
            self::UNF_ATTRIBUTE => $technicalIssue ? '1' : '0',
        ];

        return new class ($resourceIds, $attributes, $recurring, $referenceNumber) {
            public function __construct(
                private array $resourceIds,
                private array $attributes,
                private bool $recurring,
                private string $referenceNumber
            ) {
            }

            public function AllResourceIds()
            {
                return $this->resourceIds;
            }

            public function GetAttributeValue($customAttributeId)
            {
                return $this->attributes[$customAttributeId] ?? null;
            }

            public function IsRecurring()
            {
                return $this->recurring;
            }

            public function CurrentInstance()
            {
                $referenceNumber = $this->referenceNumber;
                return new class ($referenceNumber) {
                    public function __construct(private string $referenceNumber)
                    {
                    }

                    public function ReferenceNumber()
                    {
                        return $this->referenceNumber;
                    }
                };
            }
        };
    }
}
