<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * Attribute store backed by LibreBooking's attribute repository and parameterised commands.
 */
class LibreBookingAttributeStore
{
    public function read(int $seriesId, int $attributeId): ?string
    {
        $values = (new \AttributeRepository())->GetEntityValues(\CustomAttributeCategory::RESERVATION, $seriesId);
        foreach ($values as $value) {
            if ((int) $value->AttributeId === $attributeId) {
                return $value->Value;
            }
        }

        return null;
    }

    public function set(int $seriesId, int $attributeId, string $value): void
    {
        $this->remove($seriesId, $attributeId);
        \ServiceLocator::GetDatabase()->Query(
            new \AddAttributeValueCommand($attributeId, $value, $seriesId, \CustomAttributeCategory::RESERVATION)
        );
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
        \ServiceLocator::GetDatabase()->Query(new \RemoveAttributeValueCommand($attributeId, $seriesId));
    }
}
