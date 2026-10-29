<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/Validation/namespace.php');
require_once(ROOT_DIR . 'plugins/PreReservation/EnforceStudiesName/EnforceStudiesName.php');

use LibreBooking\Studies\StudyCatalog;

class EnforceStudiesNameTest extends TestBase
{
    private const URL = 'https://example.test/studies.json';
    private const ATTRIBUTE_ID = 5;
    private const MESSAGE = 'Study name is not valid.';
    private const UNAVAILABLE = 'Studies unavailable.';

    private string $cacheFile;

    public function setUp(): void
    {
        parent::setUp();

        // a fresh cache keeps the test offline; the plugin uses the same file name for this URL
        $this->cacheFile = sys_get_temp_dir() . '/librebooking-enforce-studies-name-' . sha1(self::URL) . '.json';
        file_put_contents($this->cacheFile, '{"ant": {"regex": "^ANT_[0-9]{3}$"}}');
    }

    public function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function testAddRejectsStudyNameMatchingNoStudy()
    {
        $this->configure((string) self::ATTRIBUTE_ID);
        $plugin = new EnforceStudiesName($this->factoryReturning('CreatePreAddService', $this->validBase()));

        $result = $plugin->CreatePreAddService($this->createMock(UserSession::class))->Validate($this->series('ANT_01'));

        $this->assertFalse($result->CanBeSaved());
        $this->assertSame([self::MESSAGE], $result->GetErrors());
    }

    public function testAddAcceptsStudyNameMatchingAStudy()
    {
        $this->configure((string) self::ATTRIBUTE_ID);
        $plugin = new EnforceStudiesName($this->factoryReturning('CreatePreAddService', $this->validBase()));

        $result = $plugin->CreatePreAddService($this->createMock(UserSession::class))->Validate($this->series('ANT_001'));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testUpdateIsValidatedToo()
    {
        $this->configure((string) self::ATTRIBUTE_ID);
        $plugin = new EnforceStudiesName($this->factoryReturning('CreatePreUpdateService', $this->validBase()));

        $result = $plugin->CreatePreUpdateService($this->createMock(UserSession::class))->Validate($this->series('nope'));

        $this->assertFalse($result->CanBeSaved());
    }

    public function testBaseServiceIsReturnedUnchangedWhenNoAttributeIsConfigured()
    {
        $this->configure('');
        $base = $this->validBase();
        $plugin = new EnforceStudiesName($this->factoryReturning('CreatePreAddService', $base));

        $this->assertSame($base, $plugin->CreatePreAddService($this->createMock(UserSession::class)));
    }

    public function testDeleteIsNotValidated()
    {
        $this->configure((string) self::ATTRIBUTE_ID);
        $base = $this->validBase();
        $plugin = new EnforceStudiesName($this->factoryReturning('CreatePreDeleteService', $base));

        $this->assertSame($base, $plugin->CreatePreDeleteService($this->createMock(UserSession::class)));
    }

    private function configure(string $attributeIds): void
    {
        $file = new FakeConfigFile();
        $file->SetKey(EnforceStudiesNameConfigKeys::STUDIES_URL, self::URL);
        $file->SetKey(EnforceStudiesNameConfigKeys::CUSTOM_ATTRIBUTE_IDS, $attributeIds);
        $file->SetKey(EnforceStudiesNameConfigKeys::CACHE_TTL_SECONDS, 3600);
        $file->SetKey(EnforceStudiesNameConfigKeys::MESSAGE, self::MESSAGE);
        $file->SetKey(EnforceStudiesNameConfigKeys::UNAVAILABLE_MESSAGE, self::UNAVAILABLE);
        $this->fakeConfig->SetFile(EnforceStudiesNameConfigKeys::CONFIG_ID, $file);
    }

    private function validBase(): IReservationValidationService
    {
        $base = $this->createMock(IReservationValidationService::class);
        $base->method('Validate')->willReturn(new ReservationValidationResult());
        return $base;
    }

    private function factoryReturning(string $method, IReservationValidationService $service): PreReservationFactory
    {
        $factory = $this->createMock(PreReservationFactory::class);
        $factory->method($method)->willReturn($service);
        return $factory;
    }

    /**
     * @return object a reservation series holding one study name in the configured attribute
     */
    private function series(string $studyName): object
    {
        return new class ($studyName) {
            public function __construct(private string $studyName)
            {
            }

            public function GetAttributeValue($customAttributeId)
            {
                return $customAttributeId === 5 ? $this->studyName : null;
            }
        };
    }
}
