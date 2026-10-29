<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\ScreeningLinks;
use TestBase;

class ScreeningLinksTest extends TestBase
{
    public function testSurveyTokenUrlIsStoredWithoutIndexPhp()
    {
        $this->assertSame('https://ls/12/token/REF-1', ScreeningLinks::surveyTokenUrl('https://ls', 12, 'REF-1'));
    }

    public function testIndexedSurveyTokenUrlIncludesIndexPhp()
    {
        $this->assertSame('https://ls/index.php/12/token/REF-1', ScreeningLinks::indexedSurveyTokenUrl('https://ls', 12, 'REF-1'));
    }

    public function testResponseUrlLeadsToTheAnswersInTheDataEntry()
    {
        $this->assertSame(
            'https://ls/index.php/admin/dataentry/sa/editdata/subaction/edit/surveyid/12/id/345',
            ScreeningLinks::responseUrl('https://ls', 12, 345)
        );
    }

    public function testCompletedTextIsPlainText()
    {
        $this->assertSame('Screen Done', ScreeningLinks::completedText('Screen', 'Done'));
    }

    public function testNotCompletedTextIsPlainText()
    {
        $this->assertSame('Screen Not done', ScreeningLinks::notCompletedText('Screen', 'Not done'));
    }

    public function testResponseUrlAndCompletedTextMarkACompletedScreening()
    {
        $this->assertTrue(ScreeningLinks::isCompleted(ScreeningLinks::responseUrl('https://ls', 12, 5), 'Screen Done', 'https://ls', 12));
        $this->assertTrue(ScreeningLinks::isCompleted('Screen Done', 'Screen Done', 'https://ls', 12));
    }

    public function testOpenScreeningLinksAreNotCompleted()
    {
        $this->assertFalse(ScreeningLinks::isCompleted(ScreeningLinks::surveyTokenUrl('https://ls', 12, 'REF-1'), 'Screen Done', 'https://ls', 12));
        $this->assertFalse(ScreeningLinks::isCompleted(ScreeningLinks::notCompletedText('Screen', 'Not done'), 'Screen Done', 'https://ls', 12));
    }

    public function testResponseOfAnotherSurveyDoesNotMarkTheScreeningCompleted()
    {
        $this->assertFalse(ScreeningLinks::isCompleted(ScreeningLinks::responseUrl('https://ls', 99, 5), 'Screen Done', 'https://ls', 12));
    }
}
