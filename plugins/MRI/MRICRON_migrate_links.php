<?php

/**
 * One-off migration: rewrites the screening and logbook link attributes of all future reservations in the current
 * format, after checking in LimeSurvey that each non-phantom reservation has its screening participant.
 *
 * Dry run by default; nothing is written until --apply is given:
 *   php plugins/MRI/MRICRON_migrate_links.php
 *   php plugins/MRI/MRICRON_migrate_links.php --apply
 */

require_once(__DIR__ . '/MriCronBootstrap.php');

use LibreBooking\Mri\Cron\LinkMigrationJob;

$apply = in_array('--apply', $argv ?? [], true);
$app = mriCronBootstrap();

if ($app['settings']->screeningLinkAttributeId === 0 || $app['settings']->logbookLinkAttributeId === 0) {
    fwrite(STDERR, "The screening or logbook link attribute label matches no reservation attribute; see the log\n");
    exit(1);
}

$report = (new LinkMigrationJob(
    $app['remote'],
    $app['lookup'],
    $app['reservations'],
    $app['attributes'],
    $app['settings'],
))->run(new DateTimeImmutable('now'), $apply);

$counts = [];
foreach ($report as $row) {
    $counts[$row['outcome']] = ($counts[$row['outcome']] ?? 0) + 1;
    if ($row['outcome'] !== LinkMigrationJob::UNCHANGED) {
        echo sprintf("%-20s %-24s %-28s %s\n", $row['outcome'], $row['reference'], $row['session'], $row['detail']);
    }
}

echo ($apply ? 'APPLIED' : 'DRY RUN (use --apply to write)') . ': ' . count($report) . " future reservations\n";
foreach ($counts as $outcome => $count) {
    echo "  $outcome: $count\n";
}
