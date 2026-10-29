<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\ScreeningLookup;
use LibreBooking\Tests\Plugins\MRI\Fakes\FakeLimesurvey;
use LibreBooking\Tests\Plugins\MRI\Fakes\TestSupport;
use TestBase;

class ScreeningLookupTest extends TestBase
{
    public function testUnknownSessionNameIsNotLookedUp()
    {
        $fake = FakeLimesurvey::withDefaults();

        $state = $this->lookup($fake)->stateFor('KEY', TestSupport::reservation('NOPE', 'REF-1', 3));

        $this->assertNull($state);
        $this->assertSame([], $fake->methods());
    }

    public function testMultiSessionWithOwnParticipantDoesNotLookUpByName()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => fn (array $params) => ['token' => 'REF-2', 'tid' => '4', 'completed' => 'Y'],
        ]);

        $state = $this->lookup($fake)->stateFor('KEY', TestSupport::reservation('HOF_001_pre1', 'REF-2', 3));

        $this->assertTrue($state->completed);
        $this->assertSame(1, $this->countCalls($fake, 'get_participant_properties'));
    }

    public function testMultiSessionLaterVisitIsLookedUpByParticipantName()
    {
        $fake = FakeLimesurvey::withDefaults([
            'get_participant_properties' => function (array $params): array {
                return isset($params[2]['firstname'])
                    ? ['token' => 'REF-1', 'tid' => '3', 'completed' => 'N']
                    : ['status' => 'Error: No results were found based on your attributes.', 'error_code' => 'ERR_NOT_FOUND'];
            },
        ]);

        $state = $this->lookup($fake)->stateFor('KEY', TestSupport::reservation('HOF_001_pre2', 'REF-3', 3));

        $this->assertTrue($state->isMultiX);
        $this->assertSame('REF-1', $state->participantToken);
        $this->assertSame(2, $this->countCalls($fake, 'get_participant_properties'));
    }

    private function lookup(FakeLimesurvey $fake): ScreeningLookup
    {
        $remote = new RemoteControl('https://ls', 'user', 'pass', $fake->transport());

        return new ScreeningLookup($remote, TestSupport::SCREENING_SURVEY, TestSupport::catalog());
    }

    private function countCalls(FakeLimesurvey $fake, string $method): int
    {
        return count(array_filter($fake->calls, fn (array $call): bool => $call[0] === $method));
    }
}
