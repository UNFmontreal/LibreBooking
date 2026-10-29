<?php

/**
 * Cron job: reminds the MRI operator to complete the logbook entry of reservations that have finished.
 *
 * Run once a day from the command line: php plugins/MRI/MRICRON_reminder_logbook.php
 */

require_once(__DIR__ . '/MriCronBootstrap.php');

$app = mriCronBootstrap();

(new LibreBooking\Mri\Cron\LogbookReminderJob(
    $app['remote'],
    $app['reservations'],
    $app['mailer'],
    $app['settings'],
))->run(new DateTimeImmutable('now'));
