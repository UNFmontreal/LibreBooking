<?php

declare(strict_types=1);

namespace LibreBooking\Mri\Cron;

use DateTimeImmutable;
use LibreBooking\Limesurvey\Participant;
use LibreBooking\Limesurvey\RemoteControl;
use LibreBooking\Mri\LibreBookingAttributeStore;
use LibreBooking\Mri\LibreBookingReservationSource;
use LibreBooking\Mri\MriReservation;
use LibreBooking\Mri\ScreeningLinks;
use LibreBooking\Mri\ScreeningLookup;
use LibreBooking\Mri\ScreeningState;
use LibreBooking\Mri\Settings;

/**
 * One-off migration of the screening and logbook link attributes of future reservations to the current format.
 *
 * Every future reservation of the monitored resources is checked against LimeSurvey and its links are rebuilt the way
 * the post-reservation notifiers and the valid screening cron write them:
 *  - a phantom reservation has no screening participant, so its screening link says the screening is completed;
 *  - a screened reservation links to the answers of its screening (or says completed when it has none);
 *  - a reservation still to be screened links to the survey token page, or says not completed when a later visit of a
 *    multi-session study relies on the first visit.
 * The logbook link is only built for reservations whose screening is completed, like the notifiers do.
 *
 * A non-phantom reservation without a screening participant in LimeSurvey is reported and left unchanged. LimeSurvey is
 * only read, never written.
 */
final class LinkMigrationJob
{
    public const UPDATED = 'updated';
    public const UNCHANGED = 'unchanged';
    public const MISSING_PARTICIPANT = 'missing-participant';
    public const NO_STUDY = 'no-study';

    private const FAR_FUTURE = '+20 years';

    public function __construct(
        private readonly RemoteControl $remote,
        private readonly ScreeningLookup $lookup,
        private readonly LibreBookingReservationSource $reservations,
        private readonly LibreBookingAttributeStore $attributes,
        private readonly Settings $settings,
    ) {
    }

    /**
     * @param bool $apply false only reports what would change
     * @return list<array{reference: string, session: string, outcome: string, detail: string}>
     */
    public function run(DateTimeImmutable $now, bool $apply): array
    {
        $resourceIds = $this->reservations->resourceIdsByName($this->settings->monitoredResourceNames);
        $until = $now->modify(self::FAR_FUTURE);

        $future = array_merge(
            $this->reservations->startingBetween($now, $until, $resourceIds, \ReservationStatus::Created),
            $this->reservations->startingBetween($now, $until, $resourceIds, \ReservationStatus::Pending),
        );
        if (empty($future)) {
            return [];
        }

        return $this->remote->withSession(function (string $sessionKey) use ($future, $apply): array {
            $report = [];
            foreach ($future as $reservation) {
                $report[] = $this->migrate($sessionKey, $reservation, $apply);
            }

            return $report;
        });
    }

    /**
     * @return array{reference: string, session: string, outcome: string, detail: string}
     */
    private function migrate(string $sessionKey, MriReservation $reservation, bool $apply): array
    {
        $row = fn (string $outcome, string $detail = ''): array => [
            'reference' => $reservation->referenceNumber,
            'session' => $reservation->sessionName,
            'outcome' => $outcome,
            'detail' => $detail,
        ];

        if ($reservation->isPhantom) {
            return $this->store($reservation, $this->phantomLinks($reservation), $apply, $row);
        }

        $state = $this->lookup->stateFor($sessionKey, $reservation);
        if ($state === null) {
            return $row(self::NO_STUDY, 'no study matches the session name');
        }
        if ($state->participantToken === null || !$this->participantExists($sessionKey, $state->participantToken)) {
            return $row(self::MISSING_PARTICIPANT, 'no screening participant in LimeSurvey');
        }

        $links = $state->completed
            ? $this->completedLinks($sessionKey, $reservation, $state)
            : ['screening' => $this->pendingScreeningLink($reservation, $state), 'logbook' => null];

        return $this->store($reservation, $links, $apply, $row);
    }

    /**
     * @param array{screening: string, logbook: ?string} $links
     * @param callable(string, string=): array{reference: string, session: string, outcome: string, detail: string} $row
     * @return array{reference: string, session: string, outcome: string, detail: string}
     */
    private function store(MriReservation $reservation, array $links, bool $apply, callable $row): array
    {
        $changes = [];
        $targets = [
            'screening' => $this->settings->screeningLinkAttributeId,
            'logbook' => $this->settings->logbookLinkAttributeId,
        ];

        foreach ($targets as $name => $attributeId) {
            $wanted = $links[$name];
            if ($wanted === null || $attributeId === 0) {
                continue;
            }
            if ($this->attributes->read($reservation->seriesId, $attributeId) === $wanted) {
                continue;
            }

            $changes[] = $name . ' link';
            if ($apply) {
                $this->attributes->set($reservation->seriesId, $attributeId, $wanted);
            }
        }

        return empty($changes)
            ? $row(self::UNCHANGED)
            : $row(self::UPDATED, implode(' and ', $changes) . ($apply ? '' : ' (dry run)'));
    }

    /**
     * @return array{screening: string, logbook: string}
     */
    private function phantomLinks(MriReservation $reservation): array
    {
        return [
            'screening' => ScreeningLinks::completedText($this->settings->screeningLinkText, $this->settings->completedText),
            'logbook' => $this->logbookUrl($reservation, null, [0, 0]),
        ];
    }

    /**
     * @return array{screening: string, logbook: string}
     */
    private function completedLinks(string $sessionKey, MriReservation $reservation, ScreeningState $state): array
    {
        $responseIds = $this->responseIds($sessionKey, (string) $state->participantToken);

        return [
            'screening' => $responseIds[0] > 0
                ? ScreeningLinks::responseUrl($this->settings->lsBaseUrl, $this->settings->screeningSurveyId, $responseIds[0])
                : ScreeningLinks::completedText($this->settings->screeningLinkText, $this->settings->completedText),
            'logbook' => $this->logbookUrl($reservation, $state, $responseIds),
        ];
    }

    private function pendingScreeningLink(MriReservation $reservation, ScreeningState $state): string
    {
        // a later visit of a multi-session study is covered by the first visit, so it has no link of its own
        if ($state->isMultiX) {
            return ScreeningLinks::notCompletedText($this->settings->screeningLinkText, $this->settings->notCompletedText);
        }

        return ScreeningLinks::surveyTokenUrl($this->settings->lsBaseUrl, $this->settings->screeningSurveyId, $reservation->referenceNumber);
    }

    private function participantExists(string $sessionKey, string $token): bool
    {
        return Participant::fromProperties(
            $this->remote->participant($sessionKey, $this->settings->screeningSurveyId, ['token' => $token])
        ) !== null;
    }

    /**
     * The ids of the first two complete responses, read only.
     *
     * @return int[] [first, second], 0 when a response does not exist
     */
    private function responseIds(string $sessionKey, string $token): array
    {
        $existing = $this->remote->responseIds($sessionKey, $this->settings->screeningSurveyId, $token);
        if (empty($existing)) {
            return [0, 0];
        }

        $groups = $this->remote->exportResponses($sessionKey, $this->settings->screeningSurveyId, $token, 'complete');

        return [
            RemoteControl::firstResponseId($groups[0] ?? []) ?? 0,
            RemoteControl::firstResponseId($groups[1] ?? []) ?? 0,
        ];
    }

    /**
     * @param int[] $responseIds
     */
    private function logbookUrl(MriReservation $reservation, ?ScreeningState $state, array $responseIds): string
    {
        return strtr($this->settings->linkToLogbook, [
            '[LS_BASEURL]' => $this->settings->lsBaseUrl,
            '[LogbookSurveyID]' => (string) $this->settings->logbookSurveyId,
            // the logbook of a reservation is recorded against its own token, as the post-reservation notifiers do
            '[TOKEN]' => $reservation->referenceNumber,
            '[session_name]' => urlencode($reservation->sessionName),
            '[RA]' => urlencode($reservation->ra),
            '[START_TIME]' => $reservation->start->format('H:i'),
            '[END_TIME]' => $reservation->end->format('H:i'),
            '[DATE]' => $reservation->start->format('Y-m-d'),
            '[ETHIC]' => urlencode((string) $state?->ethic),
            '[URL_ID]' => (string) $responseIds[0],
            '[URL_ID2]' => (string) $responseIds[1],
        ]);
    }
}
