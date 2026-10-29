<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/Validation/namespace.php');
require_once(ROOT_DIR . 'plugins/PreReservation/EnforceStudiesName/EnforceStudiesNameValidation.php');

use LibreBooking\Studies\StudyCatalog;

class EnforceStudiesNameValidationTest extends TestBase
{
    private const MESSAGE = 'Study name is not valid.';
    private const UNAVAILABLE = 'Studies unavailable.';
    private const JSON = '{"ant": {"regex": "^(ANT)_([0-9]{3})$"}, "chi": {"regex": "^chi_[0-9]+$"}}';

    private string $cacheFile;

    public function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = sys_get_temp_dir() . '/enforce-studies-validation-test-' . uniqid() . '.json';
    }

    public function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function testBaseFailureIsReturnedWithoutDownloadingStudies()
    {
        $calls = 0;
        $base = $this->baseReturning(new ReservationValidationResult(false, ['base failure']));

        $validation = $this->validation($base, [1], $calls);

        $result = $validation->Validate($this->series([1 => 'bad']));

        $this->assertFalse($result->CanBeSaved());
        $this->assertSame(['base failure'], $result->GetErrors());
        $this->assertSame(0, $calls);
    }

    public function testValueMatchingStudiesIsAccepted()
    {
        $calls = 0;
        $validation = $this->validation($this->baseReturning(new ReservationValidationResult()), [1], $calls);

        $result = $validation->Validate($this->series([1 => 'ANT_001']));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testValueMatchingNoStudyIsRejected()
    {
        $calls = 0;
        $validation = $this->validation($this->baseReturning(new ReservationValidationResult()), [1], $calls);

        $result = $validation->Validate($this->series([1 => 'ANT_01']));

        $this->assertFalse($result->CanBeSaved());
        $this->assertSame([self::MESSAGE], $result->GetErrors());
    }

    public function testEveryConfiguredAttributeIsChecked()
    {
        $calls = 0;
        $validation = $this->validation($this->baseReturning(new ReservationValidationResult()), [1, 2], $calls);

        $result = $validation->Validate($this->series([1 => 'ANT_001', 2 => 'nope']));

        $this->assertFalse($result->CanBeSaved());
    }

    public function testAttributesNotConfiguredAreIgnored()
    {
        $calls = 0;
        $validation = $this->validation($this->baseReturning(new ReservationValidationResult()), [1], $calls);

        $result = $validation->Validate($this->series([1 => 'chi_7', 9 => 'anything']));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testBlankValuesAreSkippedWithoutDownloadingStudies()
    {
        $calls = 0;
        $validation = $this->validation($this->baseReturning(new ReservationValidationResult()), [1], $calls);

        $result = $validation->Validate($this->series([1 => '   ']));

        $this->assertTrue($result->CanBeSaved());
        $this->assertSame(0, $calls);
    }

    public function testUnavailableStudiesRejectWithUnavailableMessage()
    {
        $calls = 0;
        $validation = $this->validation($this->baseReturning(new ReservationValidationResult()), [1], $calls, false);

        $result = $validation->Validate($this->series([1 => 'ANT_001']));

        $this->assertFalse($result->CanBeSaved());
        $this->assertSame([self::UNAVAILABLE], $result->GetErrors());
    }

    /**
     * @param array<int, string> $values attribute id => value
     */
    private function series(array $values): object
    {
        return new class ($values) {
            public function __construct(private array $values)
            {
            }

            public function GetAttributeValue($customAttributeId)
            {
                return $this->values[$customAttributeId] ?? null;
            }
        };
    }

    private function baseReturning(ReservationValidationResult $result): IReservationValidationService
    {
        $base = $this->createMock(IReservationValidationService::class);
        $base->method('Validate')->willReturn($result);
        return $base;
    }

    /**
     * @param int[] $attributeIds
     */
    private function validation(IReservationValidationService $base, array $attributeIds, int &$calls, bool $downloadSucceeds = true): EnforceStudiesNameValidation
    {
        $fetcher = function (string $url) use (&$calls, $downloadSucceeds): ?string {
            $calls++;
            return $downloadSucceeds ? self::JSON : null;
        };

        $studies = fn (): StudyCatalog => StudyCatalog::fromDownload('https://example.test/studies.json', 3600, $this->cacheFile, $fetcher);

        return new EnforceStudiesNameValidation(
            $base,
            $attributeIds,
            $studies,
            self::MESSAGE,
            self::UNAVAILABLE
        );
    }
}
