<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Studies\StudyCatalog;
use TestBase;

class StudyCatalogDownloadTest extends TestBase
{
    private const URL = 'https://example.test/studies.json';
    private const JSON = '{"ant": {"regex": "^(ANT)_([0-9]{3})$", "multisession": 0, "gid_name": "ansaldoa", "ethic": "CER"}, "chi": {"regex": "^chi_[0-9]+$", "multisession": 1}}';

    private string $cacheFile;

    public function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = sys_get_temp_dir() . '/studies-source-test-' . uniqid() . '.json';
    }

    public function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function testParseDefinitionReturnsEveryStudyWithMetadata()
    {
        $studies = StudyCatalog::parse(self::JSON);

        $this->assertCount(2, $studies);
        $this->assertSame('^(ANT)_([0-9]{3})$', $studies[0]->regex);
        $this->assertFalse($studies[0]->multisession);
        $this->assertSame('ansaldoa', $studies[0]->gidName);
        $this->assertSame('CER', $studies[0]->ethic);
        $this->assertTrue($studies[1]->multisession);
        $this->assertNull($studies[1]->gidName);
    }

    public function testParseDefinitionReadsCaregiverScreening()
    {
        $studies = StudyCatalog::parse('{"a": {"regex": "^a$", "caregiver_screening": 2}, "b": {"regex": "^b$"}}');

        $this->assertSame(2, $studies[0]->caregiverScreening);
        $this->assertSame(0, $studies[1]->caregiverScreening);
    }

    public function testParseDefinitionSkipsEntriesWithoutUsableRegex()
    {
        $json = '{"a": {"gid": 1}, "b": {"regex": ""}, "c": {"regex": 5}, "d": {"regex": "^d$"}}';

        $studies = StudyCatalog::parse($json);

        $this->assertCount(1, $studies);
        $this->assertSame('^d$', $studies[0]->regex);
    }

    public function testParseDefinitionRemovesDuplicateRegexes()
    {
        $json = '{"a": {"regex": "^x$"}, "b": {"regex": "^x$"}}';

        $this->assertCount(1, StudyCatalog::parse($json));
    }

    public function testParseDefinitionReturnsNullForInvalidOrEmptyDocuments()
    {
        $this->assertNull(StudyCatalog::parse('not json'));
        $this->assertNull(StudyCatalog::parse('"scalar"'));
        $this->assertNull(StudyCatalog::parse('{"a": {"gid": 1}}'));
    }

    public function testDownloadIsCachedForLaterRequests()
    {
        $calls = 0;
        $fetcher = function (string $url) use (&$calls): ?string {
            $calls++;
            return self::JSON;
        };

        $first = StudyCatalog::fromDownload(self::URL, 3600, $this->cacheFile, $fetcher);
        $second = StudyCatalog::fromDownload(self::URL, 3600, $this->cacheFile, $fetcher);

        $this->assertCount(2, $first->studies());
        $this->assertCount(2, $second->studies());
        $this->assertSame(1, $calls);
        $this->assertFileExists($this->cacheFile);
    }

    public function testStudiesAreLoadedOnlyOncePerInstance()
    {
        $calls = 0;
        $fetcher = function (string $url) use (&$calls): ?string {
            $calls++;
            return self::JSON;
        };

        $source = StudyCatalog::fromDownload(self::URL, 0, $this->cacheFile, $fetcher);
        $source->studies();
        $source->studies();

        $this->assertSame(1, $calls);
    }

    public function testZeroTtlDownloadsEveryTimeAndDoesNotWriteCache()
    {
        $calls = 0;
        $fetcher = function (string $url) use (&$calls): ?string {
            $calls++;
            return self::JSON;
        };

        (StudyCatalog::fromDownload(self::URL, 0, $this->cacheFile, $fetcher))->studies();
        (StudyCatalog::fromDownload(self::URL, 0, $this->cacheFile, $fetcher))->studies();

        $this->assertSame(2, $calls);
        $this->assertFileDoesNotExist($this->cacheFile);
    }

    public function testFailedDownloadWithoutCacheReturnsNull()
    {
        $source = StudyCatalog::fromDownload(self::URL, 3600, $this->cacheFile, fn (string $url): ?string => null);

        $this->assertNull($source->studies());
    }

    public function testInvalidDownloadWithoutCacheReturnsNullAndWritesNothing()
    {
        $source = StudyCatalog::fromDownload(self::URL, 3600, $this->cacheFile, fn (string $url): ?string => 'not json');

        $this->assertNull($source->studies());
        $this->assertFileDoesNotExist($this->cacheFile);
    }

    public function testFailedDownloadFallsBackToExpiredCache()
    {
        file_put_contents($this->cacheFile, self::JSON);
        touch($this->cacheFile, time() - 7200);

        $source = StudyCatalog::fromDownload(self::URL, 3600, $this->cacheFile, fn (string $url): ?string => null);

        $this->assertCount(2, $source->studies());
    }

    public function testFreshCacheIsUsedWithoutDownloading()
    {
        file_put_contents($this->cacheFile, '{"x": {"regex": "^cached$"}}');

        $calls = 0;
        $fetcher = function (string $url) use (&$calls): ?string {
            $calls++;
            return self::JSON;
        };

        $studies = (StudyCatalog::fromDownload(self::URL, 3600, $this->cacheFile, $fetcher))->studies();

        $this->assertSame('^cached$', $studies[0]->regex);
        $this->assertSame(0, $calls);
    }
}
