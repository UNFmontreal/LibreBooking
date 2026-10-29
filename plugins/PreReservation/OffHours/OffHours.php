<?php

require_once(dirname(__FILE__) . '/OffHoursConfigKeys.php');
require_once(dirname(__FILE__) . '/OffHoursSchedule.php');
require_once(dirname(__FILE__) . '/OffHoursValidation.php');

/**
 * Restricts reservations outside the configured regular hours to members of the allowed groups.
 * Applies when reservations are added or updated.
 */
class OffHours implements IPreReservationFactory
{
    /**
     * @var IPreReservationFactory
     */
    private $factoryToDecorate;

    public function __construct(IPreReservationFactory $factoryToDecorate)
    {
        $this->factoryToDecorate = $factoryToDecorate;

        Configuration::Instance()->Register(
            dirname(__FILE__) . '/OffHours.config.php',
            __DIR__ . '/.env',
            OffHoursConfigKeys::CONFIG_ID,
            false,
            OffHoursConfigKeys::class
        );
    }

    public function CreatePreAddService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreAddService($userSession);
        return $this->WithOffHoursRule($base);
    }

    public function CreatePreUpdateService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePreUpdateService($userSession);
        return $this->WithOffHoursRule($base);
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
    private function WithOffHoursRule(IReservationValidationService $base)
    {
        $configFile = Configuration::Instance()->File(OffHoursConfigKeys::CONFIG_ID);
        $regularHours = (string) $configFile->GetKey(OffHoursConfigKeys::REGULAR_HOURS);

        // an empty regular hours setting disables the restriction
        if (trim($regularHours) === '') {
            return $base;
        }

        $allowedGroups = $this->ParseGroupNames((string) $configFile->GetKey(OffHoursConfigKeys::ALLOWED_GROUPS));
        $message = (string) $configFile->GetKey(OffHoursConfigKeys::MESSAGE);

        return new OffHoursValidation(
            $base,
            new OffHoursSchedule($regularHours),
            $allowedGroups,
            $message,
            new UserRepository(),
            new ScheduleRepository()
        );
    }

    /**
     * @param string $groups comma separated group names
     * @return string[] lowercase group names
     */
    private function ParseGroupNames(string $groups)
    {
        $names = [];
        foreach (explode(';', $groups) as $name) {
            $name = strtolower(trim($name));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
