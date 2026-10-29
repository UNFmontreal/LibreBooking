<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use LogicException;
use RuntimeException;

/**
 * Finds the response fields of the logbook survey questions from their question codes.
 *
 * LimeSurvey names the response field of a question "{survey}X{group}X{question}". Those group and question ids change
 * when the survey is imported again, but the question codes do not, so the fields are looked up from the live survey
 * and cached on disk. When the survey cannot be reached, an expired cache is used.
 */
final class LogbookQuestions
{
    public const COMMENTS = 'comments';
    public const SCAN_DONE = 'scan_done';
    public const UNF_TECHNICAL_ISSUE = 'unf_technical_issue';
    public const UPDATE_7_DAYS = 'update_7_days';
    public const PROTOCOL = 'protocol';
    public const OPERATOR = 'operator';
    public const RA = 'ra';
    public const URL_ID = 'url_id';
    public const PATIENT_ID = 'patient_id';
    public const CURR_DATE = 'curr_date';
    public const START_TIME = 'start_time';
    public const END_TIME = 'end_time';

    /**
     * The question code of the logbook survey that answers each name.
     */
    private const CODES = [
        self::COMMENTS => 'comments',
        self::SCAN_DONE => 'scanDone',
        self::UNF_TECHNICAL_ISSUE => 'unfTechnicalIssue',
        self::UPDATE_7_DAYS => 'reservationUpdated',
        self::PROTOCOL => 'bids',
        self::OPERATOR => 'operator',
        self::RA => 'RA',
        self::URL_ID => 'urlID',
        self::PATIENT_ID => 'patientID',
        self::CURR_DATE => 'currDate',
        self::START_TIME => 'startTime',
        self::END_TIME => 'endTime',
    ];

    public const DEFAULT_CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly int $surveyId,
        private readonly string $cacheFile,
        private readonly int $cacheTtlSeconds = self::DEFAULT_CACHE_TTL_SECONDS,
    ) {
    }

    /**
     * The answers of a logbook response, keyed by the response field of each question.
     *
     * @param array<string, mixed> $values answer by question name, one of the constants of this class
     * @param callable(): mixed $listQuestions returns the questions of the logbook survey (list_questions)
     * @return array<string, mixed>
     * @throws RuntimeException when the questions cannot be listed and no cached copy exists, or a question code is missing
     */
    public function answers(array $values, callable $listQuestions): array
    {
        $fields = $this->fields($listQuestions);

        $answers = [];
        foreach ($values as $name => $value) {
            $answers[$fields[$name] ?? throw new LogicException("Unknown logbook question $name")] = $value;
        }

        return $answers;
    }

    /**
     * @param callable(): mixed $listQuestions
     * @return array<string, string> response field by question name
     */
    private function fields(callable $listQuestions): array
    {
        $fresh = $this->readCache();
        if ($fresh !== null && (time() - (int) filemtime($this->cacheFile)) < $this->cacheTtlSeconds) {
            return $fresh;
        }

        try {
            $fields = $this->resolve($listQuestions());
        } catch (RuntimeException $ex) {
            if ($fresh === null) {
                throw $ex;
            }

            \Log::Error('Could not list the logbook questions, using cached copy. Error=%s', $ex->getMessage());
            return $fresh;
        }

        file_put_contents($this->cacheFile, json_encode($fields), LOCK_EX);

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    private function resolve(mixed $questions): array
    {
        if (!is_array($questions)) {
            throw new RuntimeException('LimeSurvey did not return the questions of the logbook survey');
        }

        $fieldByCode = [];
        foreach ($questions as $question) {
            if (!is_array($question) || (int) ($question['parent_qid'] ?? 0) !== 0) {
                continue;
            }

            $questionId = $question['qid'] ?? $question['id'] ?? null;
            if ($questionId === null || !isset($question['gid'], $question['title'])) {
                continue;
            }

            $fieldByCode[$question['title']] = $this->surveyId . 'X' . $question['gid'] . 'X' . $questionId;
        }

        $fields = [];
        foreach (self::CODES as $name => $code) {
            $fields[$name] = $fieldByCode[$code]
                ?? throw new RuntimeException("The logbook survey has no question with the code $code");
        }

        return $fields;
    }

    /**
     * @return array<string, string>|null
     */
    private function readCache(): ?array
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }

        $cached = json_decode((string) file_get_contents($this->cacheFile), true);

        return is_array($cached) && array_keys($cached) === array_keys(self::CODES) ? $cached : null;
    }
}
