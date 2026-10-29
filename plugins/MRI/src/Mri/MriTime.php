<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

use DateTime;
use DateTimeInterface;
use DateTimeZone;

/**
 * Converts LibreBooking times for the MRI rules. The rules work in the time zone of the MRI site.
 */
final class MriTime
{
    public const SITE_ZONE = 'America/Montreal';

    /**
     * A UTC database datetime ("Y-m-d H:i:s") as a mutable DateTime in the given zone.
     */
    public static function fromDatabase(string $utc, string $zone = self::SITE_ZONE): DateTime
    {
        $moment = new DateTime($utc . ' GMT');
        $moment->setTimeZone(new DateTimeZone($zone));

        return $moment;
    }

    /**
     * A LibreBooking date as a mutable DateTime in the given zone.
     */
    public static function fromDate(\Date $date, string $zone = self::SITE_ZONE): DateTime
    {
        return (new DateTime('@' . $date->Timestamp()))->setTimeZone(new DateTimeZone($zone));
    }

    /**
     * A moment as a LibreBooking date, for the repositories.
     */
    public static function toDate(DateTimeInterface $moment): \Date
    {
        $utc = (new \DateTimeImmutable('@' . $moment->getTimestamp()))->setTimezone(new DateTimeZone('UTC'));

        return \Date::Create(
            (int) $utc->format('Y'),
            (int) $utc->format('n'),
            (int) $utc->format('j'),
            (int) $utc->format('G'),
            (int) $utc->format('i'),
            (int) $utc->format('s'),
            'UTC'
        );
    }
}
