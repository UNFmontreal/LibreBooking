<?php

/**
 * Cron job: emails the owner of an upcoming MRI reservation until the screening is completed.
 *
 * Run once a day from the command line: php plugins/MRI/MRICRON_reminder_screening.php
 */

require_once(__DIR__ . '/MriCronBootstrap.php');

$app = mriCronBootstrap();

(new LibreBooking\Mri\Cron\ScreeningReminderJob(
    $app['remote'],
    $app['lookup'],
    $app['reservations'],
    $app['mailer'],
    $app['settings'],
))->run(new DateTimeImmutable('now'));
