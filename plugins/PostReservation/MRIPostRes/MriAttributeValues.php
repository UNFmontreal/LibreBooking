<?php

/**
 * Static access to the custom attribute values of reservation series, for the notifiers.
 *
 * Delegates to the shared attribute store in LibreBooking\Mri.
 */
final class MriAttributeValues
{
    public static function read(int $seriesId, int $attributeId): ?string
    {
        return self::store()->read($seriesId, $attributeId);
    }

    public static function set(int $seriesId, int $attributeId, string $value): void
    {
        self::store()->set($seriesId, $attributeId, $value);
    }

    public static function replaceIfPresent(int $seriesId, int $attributeId, string $value, ?string $unlessEqualTo = null): void
    {
        self::store()->replaceIfPresent($seriesId, $attributeId, $value, $unlessEqualTo);
    }

    public static function remove(int $seriesId, int $attributeId): void
    {
        self::store()->remove($seriesId, $attributeId);
    }

    private static function store(): \LibreBooking\Mri\LibreBookingAttributeStore
    {
        return new \LibreBooking\Mri\LibreBookingAttributeStore();
    }
}
