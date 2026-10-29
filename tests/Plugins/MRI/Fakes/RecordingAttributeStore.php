<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Fakes;

use LibreBooking\Mri\LibreBookingAttributeStore;

/**
 * In-memory attribute store that records what the jobs write.
 */
class RecordingAttributeStore extends LibreBookingAttributeStore
{
    /**
     * @var array<string, string> "seriesId:attributeId" => value
     */
    public array $values = [];

    public function read(int $seriesId, int $attributeId): ?string
    {
        return $this->values[$seriesId . ':' . $attributeId] ?? null;
    }

    public function set(int $seriesId, int $attributeId, string $value): void
    {
        $this->values[$seriesId . ':' . $attributeId] = $value;
    }

    public function replaceIfPresent(int $seriesId, int $attributeId, string $value, ?string $unlessEqualTo = null): void
    {
        $current = $this->read($seriesId, $attributeId);
        if ($current === null || $current === $unlessEqualTo) {
            return;
        }
        $this->set($seriesId, $attributeId, $value);
    }

    public function remove(int $seriesId, int $attributeId): void
    {
        unset($this->values[$seriesId . ':' . $attributeId]);
    }
}
