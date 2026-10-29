<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Limesurvey\RemoteControl;
use RuntimeException;
use TestBase;

class RemoteControlTest extends TestBase
{
    public function testWithSessionOpensRunsAndReleasesTheSession()
    {
        $calls = [];
        $remote = $this->remote($calls);

        $result = $remote->withSession(function (string $key) use (&$calls): string {
            $calls[] = ['work', []];
            return $key;
        });

        $this->assertSame('KEY', $result);
        $this->assertSame(['get_session_key', 'work', 'release_session_key'], array_column($calls, 0));
        $this->assertSame(['KEY'], $calls[2][1]);
    }

    public function testSessionIsReleasedWhenTheCallbackFails()
    {
        $calls = [];
        $remote = $this->remote($calls);

        try {
            $remote->withSession(fn () => throw new RuntimeException('boom'));
            $this->fail('exception expected');
        } catch (RuntimeException $ex) {
            $this->assertSame('release_session_key', end($calls)[0]);
        }
    }

    public function testMissingSessionKeyIsAnError()
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', fn (string $method, array $params) => ['status' => 'Invalid user']);

        $this->expectException(RuntimeException::class);
        $remote->withSession(fn () => null);
    }

    public function testParticipantLookupReturnsNullWhenNotFound()
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', fn (string $method, array $params) => ['status' => 'Error: No results were found based on your attributes.', 'error_code' => 'ERR_NOT_FOUND']);

        $this->assertNull($remote->participant('KEY', 12, ['token' => 'REF-1']));
    }

    public function testParticipantLookupPassesQueryAndProperties()
    {
        $seen = [];
        $remote = new RemoteControl('https://ls', 'user', 'pass', function (string $method, array $params) use (&$seen) {
            $seen = [$method, $params];
            return ['token' => 'REF-1', 'tid' => '3'];
        });

        $participant = $remote->participant('KEY', 12, ['firstname' => 'ANT_001'], ['token', 'tid']);

        $this->assertSame('get_participant_properties', $seen[0]);
        $this->assertSame(['KEY', 12, ['firstname' => 'ANT_001'], ['token', 'tid']], $seen[1]);
        $this->assertSame('REF-1', $participant['token']);
    }

    public function testResponseIdsAreIntegers()
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', fn (string $method, array $params) => ['11', '12']);

        $this->assertSame([11, 12], $remote->responseIds('KEY', 12, 'REF-1'));
    }

    public function testExportedResponsesAreDecodedIntoGroups()
    {
        $json = base64_encode('{"responses":[[{"id":5}]]}');
        $remote = new RemoteControl('https://ls', 'user', 'pass', fn (string $method, array $params) => $json);

        $groups = $remote->exportResponses('KEY', 12, 'REF-1', 'complete');

        $this->assertSame(5, $groups[0][0]->id);
    }

    public function testExportedResponsesHaveOneListPerGroup()
    {
        $json = base64_encode('{"responses":[[{"id":11},{"id":12}],[{"id":13}]]}');
        $remote = new RemoteControl('https://ls', 'user', 'pass', fn (string $method, array $params) => $json);

        $groups = $remote->exportResponses('KEY', 12, 'REF-1', 'complete');

        $this->assertCount(2, $groups);
        $this->assertSame(11, RemoteControl::firstResponseId($groups[0]));
        $this->assertSame(12, $groups[0][1]->id);
        $this->assertSame(13, RemoteControl::firstResponseId($groups[1]));
    }

    public function testInvalidExportHasNoGroups()
    {
        foreach (['not json', '', '{"other":1}'] as $answer) {
            $remote = new RemoteControl('https://ls', 'user', 'pass', fn (string $method, array $params) => base64_encode($answer));

            $this->assertSame([], $remote->exportResponses('KEY', 12, 'REF-1', 'complete'));
        }
    }

    public function testEmptyGroupHasNoFirstResponseId()
    {
        $this->assertNull(RemoteControl::firstResponseId([]));
    }

    public function testRefusedRequestWithAnErrorCodeIsAnError()
    {
        $remote = $this->answering(['get_response_ids' => ['status' => 'Invalid session key', 'error_code' => 'ERR_INVALID_SESSION']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('get_response_ids failed: Invalid session key (ERR_INVALID_SESSION)');

        $remote->responseIds('KEY', 12, 'REF-1');
    }

    public function testRefusedRequestOfAnOlderLimeSurveyIsAnError()
    {
        $remote = $this->answering(['add_response' => ['status' => 'No permission']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No permission');

        $remote->addResponse('KEY', 12, ['token' => 'REF-1']);
    }

    public function testRefusedLoginIsAnError()
    {
        $remote = $this->answering(['get_session_key' => ['status' => 'Invalid user name or password', 'error_code' => 'ERR_INVALID_CREDENTIALS']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid user name or password');

        $remote->withSession(fn (string $key): string => $key);
    }

    public function testProtocolErrorIsAnError()
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', function (string $method, array $params): mixed {
            throw new RuntimeException('LimeSurvey request ' . $method . ' returned an error: Method not found');
        });

        $this->expectException(RuntimeException::class);

        $remote->listQuestions('KEY', 12);
    }

    public function testParticipantThatDoesNotExistIsNull()
    {
        foreach ([
            ['status' => 'Error: No results were found based on your attributes.', 'error_code' => 'ERR_NOT_FOUND'],
            ['status' => 'Error: Invalid tokenid', 'error_code' => 'ERR_INVALID_TOKEN'],
            ['status' => 'Error: No results were found based on your attributes.'],
        ] as $answer) {
            $this->assertNull($this->answering(['get_participant_properties' => $answer])->participant('KEY', 12, ['token' => 'REF-1']));
        }
    }

    public function testParticipantLookupWithAnInvalidSessionIsAnErrorNotAMissingParticipant()
    {
        $remote = $this->answering(['get_participant_properties' => ['status' => 'Invalid session key', 'error_code' => 'ERR_INVALID_SESSION']]);

        $this->expectException(RuntimeException::class);

        $remote->participant('KEY', 12, ['token' => 'REF-1']);
    }

    public function testExportOfATokenWithoutResponsesIsEmpty()
    {
        $remote = $this->answering(['export_responses_by_token' => ['status' => 'No Response found for Token', 'error_code' => 'ERR_NOT_FOUND']]);

        $this->assertSame([], $remote->exportResponses('KEY', 12, 'REF-1', 'complete'));
    }

    public function testExportWithAnInvalidSessionIsAnError()
    {
        $remote = $this->answering(['export_responses_by_token' => ['status' => 'Invalid session key', 'error_code' => 'ERR_INVALID_SESSION']]);

        $this->expectException(RuntimeException::class);

        $remote->exportResponses('KEY', 12, 'REF-1', 'complete');
    }

    public function testFailureToReleaseTheSessionDoesNotHideTheResult()
    {
        $remote = $this->answering(['release_session_key' => ['status' => 'Invalid session key', 'error_code' => 'ERR_INVALID_SESSION']]);

        $this->assertSame('KEY', $remote->withSession(fn (string $key): string => $key));
    }

    public function testParticipantsAreAddedWithTheirOwnTokens()
    {
        $calls = [];
        $remote = $this->remote($calls);

        $remote->addParticipants('KEY', 12, [['token' => 'REF-1']]);

        $this->assertSame(['KEY', 12, [['token' => 'REF-1']], false], $calls[0][1]);
        $this->assertSame('add_participants', $calls[0][0]);
    }

    public function testRefusedParticipantIsNotAFailureOfTheCall()
    {
        $row = ['token' => 'REF-1', 'errors' => ['token' => ['Token "REF-1" has already been taken.']]];
        $remote = $this->answering(['add_participants' => [$row]]);

        $this->assertSame([$row], $remote->addParticipants('KEY', 12, [['token' => 'REF-1']]));
    }

    public function testParticipantPropertiesAndResponsesAreChangedWithPositionalParameters()
    {
        $calls = [];
        $remote = $this->remote($calls);

        $remote->setParticipantProperties('KEY', 12, ['token' => 'REF-1'], ['lastname' => 'REF-2']);
        $remote->updateResponse('KEY', 12, ['id' => 7, 'token' => 'REF-2']);
        $remote->listQuestions('KEY', 12);

        $this->assertSame(['set_participant_properties', ['KEY', 12, ['token' => 'REF-1'], ['lastname' => 'REF-2']]], $calls[0]);
        $this->assertSame(['update_response', ['KEY', 12, ['id' => 7, 'token' => 'REF-2']]], $calls[1]);
        $this->assertSame(['list_questions', ['KEY', 12]], $calls[2]);
    }

    /**
     * @param array<string, mixed> $answers result by method; any other method answers like a successful call
     */
    private function answering(array $answers): RemoteControl
    {
        return new RemoteControl('https://ls', 'user', 'pass', function (string $method, array $params) use ($answers): mixed {
            return array_key_exists($method, $answers) ? $answers[$method] : ($method === 'get_session_key' ? 'KEY' : null);
        });
    }

    /**
     * @param array<int, array{0: string, 1: array}> $calls filled with every call made
     */
    private function remote(array &$calls): RemoteControl
    {
        return new RemoteControl('https://ls', 'user', 'pass', function (string $method, array $params) use (&$calls) {
            $calls[] = [$method, $params];
            return $method === 'get_session_key' ? 'KEY' : null;
        });
    }
}
