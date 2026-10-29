<?php

declare(strict_types=1);

namespace LibreBooking\Limesurvey;

use RuntimeException;

/**
 * Client for the LimeSurvey RemoteControl 2 JSON-RPC API.
 *
 * @see https://www.limesurvey.org/manual/RemoteControl_2_API
 *
 * The API takes JSON-RPC 1.0 requests with positional parameters. A request that LimeSurvey refuses is still
 * answered normally: the result is then an array with a "status" text and, since LimeSurvey 5, an "error_code".
 * Such a failure is raised as a RuntimeException, except for the lookups that may find nothing, which return null
 * or an empty list instead.
 *
 * The transport performs one call and is injectable, so the calls can be tested without a LimeSurvey server.
 */
final class RemoteControl
{
    private const REMOTE_CONTROL_PATH = '/index.php/admin/remotecontrol';
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const TRANSFER_TIMEOUT_SECONDS = 10;
    private const DEFAULT_PARTICIPANT_PROPERTIES = ['email', 'firstname', 'lastname', 'token', 'tid', 'completed'];

    /**
     * The error codes of a failure that only means that nothing matches the query.
     */
    private const NOTHING_FOUND_CODES = ['ERR_NOT_FOUND', 'ERR_INVALID_TOKEN'];

    /**
     * The same failures as texts, for the LimeSurvey versions that send no error code.
     */
    private const NOTHING_FOUND_STATUSES = [
        'Error: No results were found',
        'Error: Invalid tokenid',
        'No Response found',
    ];

    /**
     * @var callable(string, array<int, mixed>): mixed
     */
    private $transport;

    /**
     * @param (callable(string, array<int, mixed>): mixed)|null $transport performs one call; defaults to HTTP
     */
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $user,
        private readonly string $password,
        ?callable $transport = null,
    ) {
        $this->transport = $transport ?? fn (string $method, array $params): mixed => $this->post($method, $params);
    }

    /**
     * Opens a session, runs the callback with its key, and releases the session afterwards.
     *
     * @template T
     * @param callable(string): T $callback
     * @return T
     */
    public function withSession(callable $callback): mixed
    {
        $sessionKey = $this->call('get_session_key', [$this->user, $this->password]);
        if (!is_string($sessionKey) || $sessionKey === '') {
            throw new RuntimeException('LimeSurvey did not return a session key');
        }

        try {
            return $callback($sessionKey);
        } finally {
            try {
                $this->call('release_session_key', [$sessionKey]);
            } catch (RuntimeException) {
                // the session expires by itself, and the outcome of the callback matters more
            }
        }
    }

    /**
     * The participant's properties, or null when no participant matches the query.
     *
     * @param array<string, mixed> $query for example ['token' => 'REF-1'] or ['firstname' => 'ANT_001']
     * @param string[] $properties
     * @return array<string, mixed>|null
     */
    public function participant(string $sessionKey, int $surveyId, array $query, array $properties = self::DEFAULT_PARTICIPANT_PROPERTIES): ?array
    {
        $result = $this->call('get_participant_properties', [$sessionKey, $surveyId, $query, $properties], true);

        return is_array($result) && isset($result['token']) ? $result : null;
    }

    /**
     * Sets properties on the one participant that matches the query.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $properties
     * @return array<string, mixed> the participant after the change
     */
    public function setParticipantProperties(string $sessionKey, int $surveyId, array $query, array $properties): array
    {
        return (array) $this->call('set_participant_properties', [$sessionKey, $surveyId, $query, $properties]);
    }

    /**
     * Adds participants with the tokens they carry. A participant that LimeSurvey refuses, for example because its
     * token exists already, comes back with an "errors" entry; this is not a failure of the call.
     *
     * @param array<int, array<string, mixed>> $participants
     * @return array<int, array<string, mixed>> the participants as added
     */
    public function addParticipants(string $sessionKey, int $surveyId, array $participants): array
    {
        return (array) $this->call('add_participants', [$sessionKey, $surveyId, $participants, false]);
    }

    /**
     * @param string[] $participantIds the ids (tid) of the participants
     */
    public function deleteParticipants(string $sessionKey, int $surveyId, array $participantIds): mixed
    {
        return $this->call('delete_participants', [$sessionKey, $surveyId, $participantIds]);
    }

    /**
     * @return int[] ids of the responses recorded for the token
     */
    public function responseIds(string $sessionKey, int $surveyId, string $token): array
    {
        $ids = $this->call('get_response_ids', [$sessionKey, $surveyId, $token]);

        return is_array($ids) ? array_map('intval', array_values($ids)) : [];
    }

    /**
     * Responses of the token in the given completion state, one list per response group.
     *
     * @param string $completion 'complete' or 'incomplete'
     * @return list<list<object>>
     */
    public function exportResponses(string $sessionKey, int $surveyId, string $token, string $completion): array
    {
        $answer = $this->call('export_responses_by_token', [$sessionKey, $surveyId, 'json', $token, null, $completion, 'code', 'short'], true);
        $decoded = json_decode((string) base64_decode(is_string($answer) ? $answer : ''));
        $groups = is_object($decoded) && isset($decoded->responses) ? (array) $decoded->responses : [];

        return array_values(array_map(fn ($group): array => array_values((array) $group), $groups));
    }

    /**
     * @param list<object> $group
     */
    public static function firstResponseId(array $group): ?int
    {
        return isset($group[0]->id) ? (int) $group[0]->id : null;
    }

    /**
     * @param array<string, mixed> $answers response field => answer, with "token" for the participant
     * @return mixed the id of the new response
     */
    public function addResponse(string $sessionKey, int $surveyId, array $answers): mixed
    {
        return $this->call('add_response', [$sessionKey, $surveyId, $answers]);
    }

    /**
     * Changes columns of an existing response, found by "id" or "token". The survey must allow editing after completion.
     *
     * @param array<string, mixed> $response for example ['id' => 12, 'token' => 'REF-2']
     */
    public function updateResponse(string $sessionKey, int $surveyId, array $response): mixed
    {
        return $this->call('update_response', [$sessionKey, $surveyId, $response]);
    }

    public function deleteResponse(string $sessionKey, int $surveyId, int $responseId): mixed
    {
        return $this->call('delete_response', [$sessionKey, $surveyId, $responseId]);
    }

    /**
     * The questions of the survey, groups and subquestions included.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listQuestions(string $sessionKey, int $surveyId): array
    {
        return (array) $this->call('list_questions', [$sessionKey, $surveyId]);
    }

    /**
     * @param array<int, mixed> $params
     * @param bool $mayFindNothing return null instead of failing when LimeSurvey answers that nothing matches
     * @throws RuntimeException when the request fails, or LimeSurvey refuses it
     */
    private function call(string $method, array $params, bool $mayFindNothing = false): mixed
    {
        $result = ($this->transport)($method, $params);
        if (!self::isFailure($result)) {
            return $result;
        }

        if ($mayFindNothing && self::foundNothing($result)) {
            return null;
        }

        $status = $result['status'];
        throw new RuntimeException(sprintf(
            'LimeSurvey request %s failed: %s%s',
            $method,
            is_string($status) ? $status : (string) json_encode($status),
            isset($result['error_code']) ? ' (' . $result['error_code'] . ')' : ''
        ));
    }

    /**
     * @phpstan-assert-if-true array{status: mixed} $result
     */
    private static function isFailure(mixed $result): bool
    {
        return is_array($result)
            && array_key_exists('status', $result)
            && $result['status'] !== 'OK'
            && (array_key_exists('error_code', $result) || count($result) === 1);
    }

    /**
     * @param array<string, mixed> $failure
     */
    private static function foundNothing(array $failure): bool
    {
        if (isset($failure['error_code'])) {
            return in_array($failure['error_code'], self::NOTHING_FOUND_CODES, true);
        }

        foreach (self::NOTHING_FOUND_STATUSES as $status) {
            if (is_string($failure['status']) && str_starts_with($failure['status'], $status)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function post(string $method, array $params): mixed
    {
        $ch = curl_init($this->baseUrl . self::REMOTE_CONTROL_PATH);
        if ($ch === false) {
            throw new RuntimeException('cURL is not available');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => (string) json_encode(['method' => $method, 'params' => $params, 'id' => 1]),
            // LimeSurvey answers a request of another content type with an empty response
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TRANSFER_TIMEOUT_SECONDS,
        ]);
        $body = curl_exec($ch);
        $problem = curl_error($ch);

        $response = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($response)) {
            throw new RuntimeException("LimeSurvey request $method failed" . ($problem !== '' ? ": $problem" : ''));
        }
        if (!empty($response['error'])) {
            $error = $response['error'];
            throw new RuntimeException("LimeSurvey request $method returned an error: " . (is_string($error) ? $error : json_encode($error)));
        }

        return $response['result'] ?? null;
    }
}
