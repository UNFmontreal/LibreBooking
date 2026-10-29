<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Fakes;

use DateTimeImmutable;
use LibreBooking\Mri\MriReservation;
use LibreBooking\Mri\Settings;
use LibreBooking\Studies\StudyDefinition;
use LibreBooking\Studies\StudyCatalog;

/**
 * Shared fixtures for the MRI tests: settings, studies and reservations.
 */
final class TestSupport
{
    public const SCREENING_SURVEY = 12;
    public const LOGBOOK_SURVEY = 34;

    public static function settings(): Settings
    {
        return new Settings(
            monitoredResourceNames: ['IRM 3T'],
            lsBaseUrl: 'https://ls',
            screeningSurveyId: self::SCREENING_SURVEY,
            logbookSurveyId: self::LOGBOOK_SURVEY,
            lookAheadDays: 7,
            phantomAttributeId: 6,
            sessionNameAttributeId: 5,
            raAttributeId: 7,
            screeningLinkAttributeId: 8,
            logbookLinkAttributeId: 9,
            screeningLinkText: 'Screening',
            completedText: 'Complete',
            notCompletedText: 'Not complete',
            linkToLogbook: 'https://ls/logbook?token=[TOKEN]&id=[URL_ID]',
            logbookLinkText: 'Logbook',
            screeningNotCompletedText: 'not yet screened',
            screeningEmailSubject: 'Screening for {SESSION}',
            screeningEmailBody: 'Hi {FIRST}, {SCREENING_NOT_COMPLETED}: {LINK}',
            logbookEmailSubject: 'Logbook for {SESSION}',
            logbookEmailBody: 'Fill {LINK} for {TOKEN}',
            logbookOperatorAddress: 'operator@example.test',
            supportName: 'MRI support',
            dateFormat: 'Y-m-d',
            timeFormat: 'H:i',
        );
    }

    public static function catalog(): StudyCatalog
    {
        return StudyCatalog::fromStudies([
            new StudyDefinition('^(?<study>ANT)_(?<subject>[0-9]{3})$', false, 'gid', 'ETHIC-1'),
            new StudyDefinition('^(?<study>HOF)_(?<subject>[0-9]{3})_(?<session>pre[0-9])$', true, 'gid', 'ETHIC-2'),
        ]);
    }

    public static function reservation(
        string $session,
        string $reference,
        int $statusId,
        bool $phantom = false,
        int $seriesId = 100,
    ): MriReservation {
        return new MriReservation(
            seriesId: $seriesId,
            referenceNumber: $reference,
            sessionName: $session,
            ra: 'RA-1',
            isPhantom: $phantom,
            ownerEmail: 'owner@example.test',
            ownerFirstName: 'Jane',
            ownerLastName: 'Doe',
            start: new DateTimeImmutable('2026-10-28 09:00:00'),
            end: new DateTimeImmutable('2026-10-28 10:30:00'),
            statusId: $statusId,
            logbookLink: 'https://ls/logbook?token=OLD',
        );
    }

    public static function base64Groups(string $json): string
    {
        return base64_encode($json);
    }
}
