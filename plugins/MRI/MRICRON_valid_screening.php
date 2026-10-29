<?php

/**
 * Cron job: marks MRI reservations as Created once their screening is completed in LimeSurvey.
 *
 * Run every hour or so from the command line: php plugins/MRI/MRICRON_valid_screening.php
 */

require_once(__DIR__ . '/MriCronBootstrap.php');

$app = mriCronBootstrap();

(new LibreBooking\Mri\Cron\ValidScreeningJob(
    $app['remote'],
    $app['lookup'],
    $app['reservations'],
    $app['attributes'],
    $app['settings'],
))->run(new DateTimeImmutable('now'));
