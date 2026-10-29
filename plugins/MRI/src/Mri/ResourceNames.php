<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * The monitored resources, which the settings name by their display names.
 */
final class ResourceNames
{
    /**
     * @return string[] trimmed, non-empty names from a comma separated list
     */
    public static function parse(string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $csv)), fn (string $name): bool => $name !== ''));
    }

    /**
     * @param string[] $names
     * @param (callable(string): object)|null $load loads the resource with a name; defaults to the resource repository.
     *        A name that matches no resource gives a resource without id, as the repository does
     * @return int[] ids of the resources that have these names; unknown names are logged and skipped
     */
    public static function idsOf(array $names, ?callable $load = null): array
    {
        $load ??= fn (string $name): object => (new \ResourceRepository())->LoadByName($name);

        $ids = [];
        foreach ($names as $name) {
            $id = (int) $load($name)->GetId();
            if ($id === 0) {
                \Log::Error('MRI: no resource is named "%s"', $name);
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
