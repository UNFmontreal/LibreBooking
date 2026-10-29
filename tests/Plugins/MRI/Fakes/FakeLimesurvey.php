<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Fakes;

/**
 * Stands in for the LimeSurvey transport: answers calls with a handler and records every call.
 */
final class FakeLimesurvey
{
    /**
     * @var list<array{0: string, 1: array<int, mixed>}>
     */
    public array $calls = [];

    /**
     * @param callable(string, array<int, mixed>): mixed $handler
     */
    public function __construct(private $handler)
    {
    }

    public function transport(): callable
    {
        return function (string $method, array $params): mixed {
            $this->calls[] = [$method, $params];

            return ($this->handler)($method, $params);
        };
    }

    /**
     * @return string[] the method names called, in order
     */
    public function methods(): array
    {
        return array_column($this->calls, 0);
    }

    /**
     * A handler that opens a session, reports no participants, and accepts everything else.
     *
     * @param array<string, callable(array<int, mixed>): mixed> $overrides
     */
    public static function withDefaults(array $overrides = []): self
    {
        return new self(function (string $method, array $params) use ($overrides): mixed {
            if (isset($overrides[$method])) {
                return $overrides[$method]($params);
            }

            return match ($method) {
                'get_session_key' => 'KEY',
                'get_participant_properties' => ['status' => 'Error: No results were found based on your attributes.', 'error_code' => 'ERR_NOT_FOUND'],
                'get_response_ids' => [],
                default => null,
            };
        });
    }
}
