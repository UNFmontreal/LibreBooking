<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'plugins/PostReservation/MRIPostRes/MriScreeningLinks.php');

class MriScreeningLinksTest extends TestBase
{
    public function testSurveyTokenUrlIsStoredWithoutIndexPhp()
    {
        $this->assertSame('https://ls/12/token/REF-1', MriScreeningLinks::surveyTokenUrl('https://ls', 12, 'REF-1'));
    }

    public function testIndexedSurveyTokenUrlIncludesIndexPhp()
    {
        $this->assertSame('https://ls/index.php/12/token/REF-1', MriScreeningLinks::indexedSurveyTokenUrl('https://ls', 12, 'REF-1'));
    }

    public function testCompletedTextIsPlainText()
    {
        $this->assertSame('Screen Done', MriScreeningLinks::completedText('Screen', 'Done'));
    }

    public function testNotCompletedTextIsPlainText()
    {
        $this->assertSame('Screen Not done', MriScreeningLinks::notCompletedText('Screen', 'Not done'));
    }
}
