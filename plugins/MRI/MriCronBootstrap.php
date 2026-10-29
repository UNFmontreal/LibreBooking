<?php

/**
 * Shared start-up of the MRI cron jobs.
 *
 * Each cron script requires this file, then builds its job from mriCronBootstrap(). The scripts run from the command
 * line only (JobCop::EnsureCommandLine), and find the LibreBooking root by walking up from this folder.
 */

if (!defined('ROOT_DIR')) {
    $root = __DIR__;
    while (!file_exists($root . '/Domain/Access/namespace.php')) {
        $parent = dirname($root);
        if ($parent === $root) {
            fwrite(STDERR, "LibreBooking root not found\n");
            exit(1);
        }
        $root = $parent;
    }
    define('ROOT_DIR', $root . '/');
}

require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Jobs/JobCop.php');
require_once(ROOT_DIR . 'vendor/autoload.php');
require_once(__DIR__ . '/autoload.php');

JobCop::EnsureCommandLine();

require_once(ROOT_DIR . 'plugins/PreReservation/LimesurveyScreeningPre/LimesurveyScreeningPreConfig.php');

use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\Cron\LogbookReminderJob;
use LibreBooking\Mri\Cron\ScreeningReminderJob;
use LibreBooking\Mri\Cron\ValidScreeningJob;
use LibreBooking\Mri\CustomAttributes;
use LibreBooking\Mri\LibreBookingAttributeStore;
use LibreBooking\Mri\MriMessages;
use LibreBooking\Mri\LibreBookingMailer;
use LibreBooking\Mri\LibreBookingReservationSource;
use LibreBooking\Mri\ResourceNames;
use LibreBooking\Mri\ScreeningLookup;
use LibreBooking\Mri\Settings;
use LibreBooking\Studies\StudyCatalog;

/**
 * @return array{settings: Settings, remote: RemoteControl, lookup: ScreeningLookup, reservations: LibreBookingReservationSource, attributes: LibreBookingAttributeStore, mailer: LibreBookingMailer}
 */
function mriCronBootstrap(): array
{
    LimesurveyScreeningPreConfig::Register();
    $setting = fn (array $definition): mixed => LimesurveyScreeningPreConfig::Get($definition);
    $attributes = new CustomAttributes();
    $attributeId = fn (array $definition): int => $attributes->idOf((string) $setting($definition));

    $settings = new Settings(
        monitoredResourceNames: ResourceNames::parse((string) $setting(LimesurveyScreeningPreConfigKeys::RESOURCE_NAMES)),
        lsBaseUrl: (string) $setting(LimesurveyScreeningPreConfigKeys::LS_BASE_URL),
        screeningSurveyId: (int) $setting(LimesurveyScreeningPreConfigKeys::SCREENING_SURVEY_ID),
        logbookSurveyId: (int) $setting(LimesurveyScreeningPreConfigKeys::LOGBOOK_SURVEY_ID),
        lookAheadDays: (int) $setting(LimesurveyScreeningPreConfigKeys::CRON_LOOK_AHEAD_DAYS),
        phantomAttributeId: $attributeId(LimesurveyScreeningPreConfigKeys::PHANTOM_ATTRIBUTE_LABEL),
        sessionNameAttributeId: $attributeId(LimesurveyScreeningPreConfigKeys::SESSION_NAME_ATTRIBUTE_LABEL),
        raAttributeId: $attributeId(LimesurveyScreeningPreConfigKeys::RESEARCH_ASSISTANT_ATTRIBUTE_LABEL),
        screeningLinkAttributeId: $attributeId(LimesurveyScreeningPreConfigKeys::SCREENING_LINK_ATTRIBUTE_LABEL),
        logbookLinkAttributeId: $attributeId(LimesurveyScreeningPreConfigKeys::LOGBOOK_LINK_ATTRIBUTE_LABEL),
        screeningLinkText: (string) MriMessages::text(MriMessages::SCREENING_LINK_TEXT),
        completedText: (string) MriMessages::text(MriMessages::COMPLETED_SURVEY_TEXT),
        notCompletedText: (string) MriMessages::text(MriMessages::NOT_COMPLETED_SURVEY_TEXT),
        linkToLogbook: (string) $setting(LimesurveyScreeningPreConfigKeys::LINK_TO_LOGBOOK),
        logbookLinkText: (string) MriMessages::text(MriMessages::LOGBOOK_LINK_TEXT),
        screeningNotCompletedText: (string) MriMessages::text(MriMessages::TEXT_SCREENING_NOT_COMPLETED),
        screeningEmailSubject: (string) MriMessages::text(MriMessages::EMAIL_SCREENING_MISSING_SUBJECT),
        screeningEmailBody: (string) MriMessages::text(MriMessages::EMAIL_SCREENING_MISSING_BODY),
        logbookEmailSubject: (string) MriMessages::text(MriMessages::LOGBOOK_EMAIL_SUBJECT),
        logbookEmailBody: (string) MriMessages::text(MriMessages::LOGBOOK_EMAIL_BODY),
        logbookOperatorAddress: (string) $setting(LimesurveyScreeningPreConfigKeys::LOGBOOK_OPERATOR_ADDRESS),
        supportName: (string) $setting(LimesurveyScreeningPreConfigKeys::SUPPORT_EMAIL_NAME),
        dateFormat: (string) $setting(LimesurveyScreeningPreConfigKeys::EMAIL_DATE_FORMAT),
        timeFormat: (string) $setting(LimesurveyScreeningPreConfigKeys::EMAIL_TIME_FORMAT),
    );

    // The session name rules come from the studies file; the catalogue keeps one copy per process.
    $catalog = StudyCatalog::forUrl((string) $setting(LimesurveyScreeningPreConfigKeys::STUDIES_URL), (int) $setting(LimesurveyScreeningPreConfigKeys::STUDIES_CACHE_TTL_SECONDS));
    if (!$catalog->isAvailable()) {
        \Log::Error('MRI cron: the studies file is unavailable; no job was run');
        exit(1);
    }

    $remote = new RemoteControl(
        $settings->lsBaseUrl,
        (string) $setting(LimesurveyScreeningPreConfigKeys::LS_USER),
        (string) $setting(LimesurveyScreeningPreConfigKeys::LS_PASSWORD),
    );

    return [
        'settings' => $settings,
        'remote' => $remote,
        'lookup' => new ScreeningLookup($remote, $settings->screeningSurveyId, $catalog),
        'reservations' => new LibreBookingReservationSource($settings),
        'attributes' => new LibreBookingAttributeStore(),
        'mailer' => new LibreBookingMailer(),
    ];
}
