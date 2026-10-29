<?php

require_once(dirname(__FILE__) . '/../../MRI/autoload.php');
require_once(dirname(__FILE__) . '/EnforceStudiesNameConfigKeys.php');
require_once(dirname(__FILE__) . '/EnforceStudiesNameValidation.php');

use LibreBooking\Studies\StudyCatalog;

/**
 * Requires configured custom attribute values to match a study name regex from a remote studies file.
 * Applies when reservations are added or updated.
 */
class EnforceStudiesName implements IPreReservationFactory
{
    /**
     * @var IPreReservationFactory
     */
    private $factoryToDecorate;

    public function __construct(IPreReservationFactory $factoryToDecorate)
    {
        $this->factoryToDecorate = $factoryToDecorate;

        Configuration::Instance()->Register(
            dirname(__FILE__) . '/EnforceStudiesName.config.php',
            __DIR__ . '/.env',
            EnforceStudiesNameConfigKeys::CONFIG_ID,
            false,
            EnforceStudiesNameConfigKeys::class
        );
    }

    public function CreatePreAddService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreAddService($userSession);
        return $this->WithStudiesNameRule($base);
    }

    public function CreatePreUpdateService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreUpdateService($userSession);
        return $this->WithStudiesNameRule($base);
    }

    public function CreatePreDeleteService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePreDeleteService($userSession);
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
    private function WithStudiesNameRule(IReservationValidationService $base): IReservationValidationService
    {
        $configFile = Configuration::Instance()->File(EnforceStudiesNameConfigKeys::CONFIG_ID);

        $attributeIds = self::ParseAttributeIds((string) $configFile->GetKey(EnforceStudiesNameConfigKeys::CUSTOM_ATTRIBUTE_IDS));
        if (empty($attributeIds)) {
            return $base;
        }

        $url = (string) $configFile->GetKey(EnforceStudiesNameConfigKeys::STUDIES_URL);
        $studies = fn (): StudyCatalog => StudyCatalog::fromDownload(
            $url,
            (int) $configFile->GetKey(EnforceStudiesNameConfigKeys::CACHE_TTL_SECONDS),
            sys_get_temp_dir() . '/librebooking-enforce-studies-name-' . sha1($url) . '.json'
        );

        return new EnforceStudiesNameValidation(
            $base,
            $attributeIds,
            $studies,
            (string) $configFile->GetKey(EnforceStudiesNameConfigKeys::MESSAGE),
            (string) $configFile->GetKey(EnforceStudiesNameConfigKeys::UNAVAILABLE_MESSAGE)
        );
    }

    /**
     * @return int[]
     */
    private static function ParseAttributeIds(string $csv): array
    {
        $ids = [];
        foreach (explode(',', $csv) as $item) {
            $item = trim($item);
            if (ctype_digit($item)) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique($ids));
    }
}
