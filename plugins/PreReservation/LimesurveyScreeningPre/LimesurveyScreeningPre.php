<?php

require_once(dirname(__FILE__) . '/../../MRI/autoload.php');
require_once(dirname(__FILE__) . '/LimesurveyScreeningPreConfig.php');
require_once(dirname(__FILE__) . '/LimesurveyScreeningSettings.php');
require_once(dirname(__FILE__) . '/LimesurveyScreeningRequest.php');
require_once(dirname(__FILE__) . '/LimesurveyParticipantGateway.php');
require_once(dirname(__FILE__) . '/LimesurveyScreeningRules.php');
require_once(dirname(__FILE__) . '/LimesurveyReservationFacts.php');
require_once(dirname(__FILE__) . '/LimesurveyScreeningValidation.php');
require_once(dirname(__FILE__) . '/LimesurveyScreeningDeletion.php');

use LibreBooking\Mri\CustomAttributes;
use LibreBooking\Mri\MriMessages;
use LibreBooking\Mri\ParticipantName;
use LibreBooking\Mri\ResourceNames;
use LibreBooking\Mri\ScreeningPlanner;
use LibreBooking\Studies\StudyCatalog;

/**
 * Screens reservations of monitored resources against the LimeSurvey screening survey.
 * Applies when reservations are added or updated. Added, updated and deleted reservations are planned: the
 * MriReservationContext attached to the series carries the plan to the post-reservation notifications.
 */
class LimesurveyScreeningPre implements IPreReservationFactory
{
    /**
     * @var IPreReservationFactory
     */
    private $factoryToDecorate;

    public function __construct(IPreReservationFactory $factoryToDecorate)
    {
        $this->factoryToDecorate = $factoryToDecorate;

        LimesurveyScreeningPreConfig::Register();
    }

    public function CreatePreAddService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreAddService($userSession);
        return $this->withScreening($base, false);
    }

    public function CreatePreUpdateService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreUpdateService($userSession);
        return $this->withScreening($base, true);
    }

    public function CreatePreDeleteService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreDeleteService($userSession);
        return $this->withDeletionPlan($base);
    }

    /**
     * @param UserSession $userSession
     * @return IReservationValidationService
     */
    public function CreatePreApprovalService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePreApprovalService($userSession);
    }

    /**
     * @param UserSession $userSession
     * @return IReservationValidationService
     */
    public function CreatePreCheckinService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePreCheckinService($userSession);
    }

    /**
     * @param UserSession $userSession
     * @return IReservationValidationService
     */
    public function CreatePreCheckoutService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePreCheckoutService($userSession);
    }

    /**
     * @param IReservationValidationService $base
     * @return IReservationValidationService
     */
    private function withScreening(IReservationValidationService $base, bool $isUpdate): IReservationValidationService
    {
        $configFile = LimesurveyScreeningPreConfig::File();

        $settings = $this->settings($configFile);
        if ($settings === null) {
            return $base;
        }

        $gateway = $this->gateway($configFile);
        $studies = $this->studies($configFile);

        $rules = new LimesurveyScreeningRules(
            $gateway,
            MriMessages::text(MriMessages::SCREENING_PHANTOM_MULTI),
            MriMessages::text(MriMessages::SCREENING_RECURRING),
            MriMessages::text(MriMessages::SCREENING_DUPLICATE_PARTICIPANT),
            MriMessages::text(MriMessages::SCREENING_RESOURCE_CHANGE),
        );

        return new LimesurveyScreeningValidation(
            $base,
            $settings,
            $isUpdate,
            $studies,
            $rules,
            new ReservationRepository(),
            $this->planner($gateway, $settings, $studies)
        );
    }

    /**
     * @param IReservationValidationService $base
     * @return IReservationValidationService
     */
    private function withDeletionPlan(IReservationValidationService $base): IReservationValidationService
    {
        $configFile = LimesurveyScreeningPreConfig::File();

        $settings = $this->settings($configFile);
        if ($settings === null) {
            return $base;
        }

        $studies = $this->studies($configFile);

        return new LimesurveyScreeningDeletion(
            $base,
            $settings,
            $studies,
            $this->planner($this->gateway($configFile), $settings, $studies)
        );
    }

    /**
     * @return LimesurveyScreeningSettings|null null when no resource is monitored
     */
    private function settings($configFile): ?LimesurveyScreeningSettings
    {
        $resourceNames = ResourceNames::parse((string) $configFile->GetKey(LimesurveyScreeningPreConfigKeys::RESOURCE_NAMES));
        $monitoredResourceIds = ResourceNames::idsOf($resourceNames);
        if (empty($monitoredResourceIds)) {
            if (!empty($resourceNames)) {
                Log::Error('LimesurveyScreeningPre is not screening anything: no resource is named %s', implode(', ', $resourceNames));
            }

            return null;
        }

        $attributes = new CustomAttributes();
        $attributeId = fn (array $label): int => $attributes->idOf((string) $configFile->GetKey($label));

        return new LimesurveyScreeningSettings(
            monitoredResourceIds: $monitoredResourceIds,
            sessionNameAttributeId: $attributeId(LimesurveyScreeningPreConfigKeys::SESSION_NAME_ATTRIBUTE_LABEL),
            phantomAttributeId: $attributeId(LimesurveyScreeningPreConfigKeys::PHANTOM_ATTRIBUTE_LABEL),
            sessionNameRequiredMessage: MriMessages::text(MriMessages::SCREENING_SESSION_NAME_REQUIRED),
            invalidSessionNameMessage: MriMessages::text(MriMessages::SESSION_NAME_ERROR),
            unavailableMessage: MriMessages::text(MriMessages::SCREENING_UNAVAILABLE),
            unfTechnicalIssueAttributeId: $attributeId(LimesurveyScreeningPreConfigKeys::UNF_TECHNICAL_ISSUE_ATTRIBUTE_LABEL),
        );
    }

    /**
     * @return callable(): StudyCatalog loads the studies on first use, so no download happens for unscreened reservations
     */
    private function studies($configFile): callable
    {
        $url = (string) $configFile->GetKey(LimesurveyScreeningPreConfigKeys::STUDIES_URL);

        $catalog = null;

        return function () use (&$catalog, $url, $configFile): StudyCatalog {
            return $catalog ??= StudyCatalog::fromDownload(
                $url,
                (int) $configFile->GetKey(LimesurveyScreeningPreConfigKeys::STUDIES_CACHE_TTL_SECONDS),
                sys_get_temp_dir() . '/librebooking-limesurvey-screening-studies-' . sha1($url) . '.json'
            );
        };
    }

    /**
     * @param callable(): StudyCatalog $studies
     */
    private function planner(LimesurveyParticipantGateway $gateway, LimesurveyScreeningSettings $settings, callable $studies): ScreeningPlanner
    {
        // the participant of another reservation is read from its session name with the same study regexes
        $participantOf = function (string $sessionName) use ($studies): ?string {
            $match = $studies()->find($sessionName);

            return $match === null ? null : ParticipantName::resolve($match, $sessionName);
        };

        return new ScreeningPlanner($gateway, $settings->sessionNameAttributeId, null, $participantOf);
    }

    private function gateway($configFile): LimesurveyParticipantGateway
    {
        return new LimesurveyParticipantGateway(
            (string) $configFile->GetKey(LimesurveyScreeningPreConfigKeys::LS_BASE_URL),
            (string) $configFile->GetKey(LimesurveyScreeningPreConfigKeys::LS_USER),
            (string) $configFile->GetKey(LimesurveyScreeningPreConfigKeys::LS_PASSWORD),
            (int) $configFile->GetKey(LimesurveyScreeningPreConfigKeys::SCREENING_SURVEY_ID)
        );
    }
}
