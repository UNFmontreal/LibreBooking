<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\LogbookQuestions;
use LogicException;
use RuntimeException;
use TestBase;

class LogbookQuestionsTest extends TestBase
{
    private string $cacheFile;

    public function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = sys_get_temp_dir() . '/logbook-questions-test-' . uniqid() . '.json';
    }

    public function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function testAnswersAreKeyedByTheResponseFieldOfEachQuestionCode()
    {
        $answers = $this->questions()->answers(
            [LogbookQuestions::SCAN_DONE => 'Y', LogbookQuestions::PROTOCOL => 'ANT_001', LogbookQuestions::RA => 'jdoe'],
            fn () => $this->survey()
        );

        $this->assertSame(['141234X3X73' => 'Y', '141234X2X64' => 'ANT_001', '141234X2X56' => 'jdoe'], $answers);
    }

    public function testQuestionsListedWithAnIdKeyAreSupported()
    {
        $survey = [['id' => 9, 'gid' => 4, 'title' => 'comments', 'parent_qid' => 0]] + $this->survey();
        unset($survey[0]['qid']);

        $answers = $this->questions()->answers([LogbookQuestions::COMMENTS => 'hi'], fn () => $survey);

        $this->assertSame(['141234X4X9' => 'hi'], $answers);
    }

    public function testSubquestionsAreIgnored()
    {
        $survey = $this->survey();
        $survey[] = ['qid' => 99, 'gid' => 2, 'title' => 'comments', 'parent_qid' => 59];

        $answers = $this->questions()->answers([LogbookQuestions::COMMENTS => 'hi'], fn () => $survey);

        $this->assertSame(['141234X2X59' => 'hi'], $answers);
    }

    public function testQuestionFieldsAreCachedForLaterCalls()
    {
        $calls = 0;
        $list = function () use (&$calls): array {
            $calls++;
            return $this->survey();
        };

        $this->questions()->answers([LogbookQuestions::COMMENTS => 'a'], $list);
        $this->questions()->answers([LogbookQuestions::COMMENTS => 'b'], $list);

        $this->assertSame(1, $calls);
        $this->assertFileExists($this->cacheFile);
    }

    public function testExpiredCacheIsRefreshedFromTheSurvey()
    {
        $this->questions()->answers([LogbookQuestions::COMMENTS => 'a'], fn () => $this->survey());
        touch($this->cacheFile, time() - 7200);

        $moved = array_map(fn (array $q): array => $q['title'] === 'comments' ? ['gid' => 7, 'qid' => 500] + $q : $q, $this->survey());
        $answers = $this->questions()->answers([LogbookQuestions::COMMENTS => 'a'], fn () => $moved);

        $this->assertSame(['141234X7X500' => 'a'], $answers);
    }

    public function testExpiredCacheIsUsedWhenTheSurveyCannotBeReached()
    {
        $this->questions()->answers([LogbookQuestions::COMMENTS => 'a'], fn () => $this->survey());
        touch($this->cacheFile, time() - 7200);

        $answers = $this->questions()->answers([LogbookQuestions::COMMENTS => 'a'], fn () => ['status' => 'Invalid session key']);

        $this->assertSame(['141234X2X59' => 'a'], $answers);
    }

    public function testUnreachableSurveyWithoutCacheIsAnError()
    {
        $this->expectException(RuntimeException::class);

        $this->questions()->answers([LogbookQuestions::COMMENTS => 'a'], fn () => ['status' => 'Invalid session key']);
    }

    public function testMissingQuestionCodeIsAnError()
    {
        $survey = array_values(array_filter($this->survey(), fn (array $q): bool => $q['title'] !== 'urlID'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('urlID');

        $this->questions()->answers([LogbookQuestions::COMMENTS => 'a'], fn () => $survey);
    }

    public function testUnknownQuestionNameIsAnError()
    {
        $this->expectException(LogicException::class);

        $this->questions()->answers(['nope' => 'a'], fn () => $this->survey());
    }

    private function questions(): LogbookQuestions
    {
        return new LogbookQuestions(141234, $this->cacheFile);
    }

    /**
     * The logbook survey: group 2 "MRI" and group 3 "MRI Operator".
     *
     * @return list<array<string, mixed>>
     */
    private function survey(): array
    {
        $questions = [
            [2, 59, 'comments'], [3, 73, 'scanDone'], [3, 74, 'unfTechnicalIssue'], [3, 75, 'reservationUpdated'],
            [2, 64, 'bids'], [3, 63, 'operator'], [2, 56, 'RA'], [3, 70, 'urlID'], [2, 55, 'patientID'],
            [2, 60, 'currDate'], [2, 61, 'startTime'], [2, 62, 'endTime'], [2, 72, 'isphantom'],
        ];

        return array_map(fn (array $q): array => ['gid' => $q[0], 'qid' => $q[1], 'title' => $q[2], 'parent_qid' => 0], $questions);
    }
}
