<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Studies\StudyDefinition;
use LibreBooking\Studies\StudyCatalog;
use TestBase;

class StudyCatalogMatchTest extends TestBase
{
    public function testMatchesNameThatFitsRegex()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^(ANT)_([0-9]{3})$')]);

        $this->assertTrue($matcher->matches('ANT_001'));
    }

    public function testRejectsNameThatDoesNotFitRegex()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^(ANT)_([0-9]{3})$')]);

        $this->assertFalse($matcher->matches('ANT_01'));
    }

    public function testRegexMustMatchWholeNameEvenWithoutAnchors()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('ANT_[0-9]{3}')]);

        $this->assertTrue($matcher->matches('ANT_001'));
        $this->assertFalse($matcher->matches('xANT_001'));
        $this->assertFalse($matcher->matches('ANT_001x'));
    }

    public function testTrailingNewlineIsNotPartOfTheMatch()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^ANT_001$')]);

        $this->assertFalse($matcher->matches("ANT_001\n"));
    }

    public function testMatchReturnsFirstMatchingStudy()
    {
        $first = $this->study('^beta_[a-z]+$', true);
        $second = $this->study('^beta_.*$', false);
        $matcher = StudyCatalog::fromStudies([$first, $second]);

        $this->assertSame($first, $matcher->find('beta_xyz')->study);
    }

    public function testMatchReturnsNullWhenNothingMatches()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^alpha_[0-9]+$')]);

        $this->assertNull($matcher->find('gamma_1'));
    }

    public function testMatchExposesNamedGroups()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^(?<study>HOF)_(?<subject>[0-9]{3})_(?<session>pre[0-9])$', true)]);

        $match = $matcher->find('HOF_001_pre2');

        $this->assertSame('HOF', $match->prefix);
        $this->assertSame('001', $match->subject);
        $this->assertSame('pre2', $match->session);
        $this->assertSame('HOF_001', $match->participantKey());
    }

    public function testUnnamedGroupsAreEmptyStrings()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^(ANT)_([0-9]{3})$')]);

        $match = $matcher->find('ANT_001');

        $this->assertSame('', $match->subject);
        $this->assertSame('', $match->session);
        $this->assertNull($match->participantKey());
    }

    public function testParticipantKeyIsOnlyDefinedForMultiSessionStudies()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^(?<study>ANT)_(?<subject>[0-9]{3})$', false)]);

        $this->assertNull($matcher->find('ANT_001')->participantKey());
    }

    public function testParticipantKeyWithoutStudyPrefixUsesSubjectOnly()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^(?<study>)(?<subject>p0[1-6])_(?<session>liris[0-9]{3})$', true)]);

        $this->assertSame('p01', $matcher->find('p01_liris001')->participantKey());
    }

    public function testRegexContainingTildeIsCompiled()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('^a~b$')]);

        $this->assertTrue($matcher->matches('a~b'));
    }

    public function testInvalidRegexIsIgnored()
    {
        $matcher = StudyCatalog::fromStudies([$this->study('(unclosed'), $this->study('^ok$')]);

        $this->assertTrue($matcher->matches('ok'));
        $this->assertFalse($matcher->matches('unclosed'));
    }

    public function testNoStudiesMatchNothing()
    {
        $matcher = StudyCatalog::fromStudies([]);

        $this->assertFalse($matcher->matches('anything'));
    }

    public function testCompileRejectsRegexThatWouldEscapeTheWrapper()
    {
        $this->assertNull(StudyCatalog::compile('abc\\'));
    }

    private function study(string $regex, bool $multisession = false): StudyDefinition
    {
        return new StudyDefinition($regex, $multisession);
    }
}
