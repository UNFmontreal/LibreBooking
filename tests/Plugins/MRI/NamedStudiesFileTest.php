<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;


use LibreBooking\Studies\StudyCatalog;
use TestBase;

/**
 * Checks the published-format studies file with named groups (tests/data/studies.named.json).
 */
class NamedStudiesFileTest extends TestBase
{
    private const FILE = ROOT_DIR . 'tests/data/studies.named.json';

    public function testEveryStudyRegexCompilesAndOnlyUsesKnownGroupNames()
    {
        $studies = StudyCatalog::parse((string) file_get_contents(self::FILE));

        $this->assertCount(80, $studies);
        foreach ($studies as $study) {
            $this->assertNotNull(StudyCatalog::compile($study->regex), $study->regex);
            preg_match_all('/\(\?P?<([A-Za-z_][A-Za-z0-9_]*)>/', $study->regex, $names);
            foreach ($names[1] as $name) {
                $this->assertContains($name, ['study', 'subject', 'session'], $study->regex);
            }
        }
    }

    public function testMultiSessionStudiesNameTheirSubject()
    {
        $studies = StudyCatalog::parse((string) file_get_contents(self::FILE));

        foreach ($studies as $study) {
            if ($study->multisession) {
                $this->assertMatchesRegularExpression('/\(\?P?<subject>/', $study->regex);
            }
        }
    }

    public function testMultiSessionVisitsShareTheSameParticipant()
    {
        $matcher = StudyCatalog::fromStudies(StudyCatalog::parse((string) file_get_contents(self::FILE)));

        $first = $matcher->find('TELE_001_v1');
        $second = $matcher->find('TELE_001_v2');

        $this->assertSame('TELE_001', $first->participantKey());
        $this->assertSame($first->participantKey(), $second->participantKey());
        $this->assertNotSame($first->session, $second->session);
    }

    public function testSubjectsThatContainEachOtherAreDifferentParticipants()
    {
        $catalog = StudyCatalog::fromStudies(StudyCatalog::parse((string) file_get_contents(self::FILE)));

        $this->assertSame('HOF_001', $catalog->find('HOF_001_pre1')->participantKey());
        $this->assertSame('HOF_001CON', $catalog->find('HOF_001CON_pre1')->participantKey());
    }

    public function testStudyWithoutAStudyGroupSharesTheParticipantBySubject()
    {
        $catalog = StudyCatalog::fromStudies(StudyCatalog::parse((string) file_get_contents(self::FILE)));

        $this->assertSame('p01', $catalog->find('p01_liris001')->participantKey());
        $this->assertSame('p01', $catalog->find('p01_harrypotter')->participantKey());
    }

    public function testAlternativeNameFormatsMayReuseGroupNames()
    {
        $catalog = StudyCatalog::fromStudies(StudyCatalog::parse((string) file_get_contents(self::FILE)));

        $first = $catalog->find('1234567_123456_V03');
        $second = $catalog->find('lego_iugm_12');
        $third = $catalog->find('JGH0001_123456_Initial_MRI');
        $fourth = $catalog->find('human_phantom_AB_UNF_20260101');

        $this->assertSame(['', '1234567', 'V03'], [$first->prefix, $first->subject, $first->session]);
        $this->assertSame(['lego_iugm', '', '12'], [$second->prefix, $second->subject, $second->session]);
        $this->assertSame(['JGH', '0001', 'Initial_MRI'], [$third->prefix, $third->subject, $third->session]);
        $this->assertSame(['', 'human_phantom_AB', 'UNF_20260101'], [$fourth->prefix, $fourth->subject, $fourth->session]);
    }

    public function testDevSessionNamesMatchTheDevStudy()
    {
        $catalog = StudyCatalog::fromStudies(StudyCatalog::parse((string) file_get_contents(self::FILE)));

        $this->assertNotNull($catalog->find('dev_12312aaaaaaaaasssssaa'));
        $this->assertNull($catalog->find('nope_12312'));
    }
}
