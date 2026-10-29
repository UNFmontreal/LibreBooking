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
     * @return int[] ids of the resources that have these names; unknown names are skipped
     */
    public static function idsOf(array $names): array
    {
        $ids = [];
        $repository = new \ResourceRepository();
        foreach ($names as $name) {
            $resource = $repository->LoadByName($name);
            if ($resource !== null) {
                $ids[] = (int) $resource->GetId();
            }
        }

        return $ids;
    }
}
