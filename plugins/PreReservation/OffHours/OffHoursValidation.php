<?php

require_once(ROOT_DIR . 'Domain/Access/namespace.php');

class OffHoursValidation implements IReservationValidationService
{
    /**
     * @var IReservationValidationService
     */
    private $serviceToDecorate;

    /**
     * @var OffHoursSchedule
     */
    private $regularHours;

    /**
     * Lowercase group names allowed to book outside regular hours
     *
     * @var string[]
     */
    private $allowedGroups;

    /**
     * @var string
     */
    private $message;

    /**
     * @var IUserRepository
     */
    private $userRepository;

    /**
     * @var IScheduleRepository
     */
    private $scheduleRepository;

    /**
     * @param IReservationValidationService $serviceToDecorate
     * @param OffHoursSchedule $regularHours
     * @param string[] $allowedGroups lowercase group names
     * @param string $message
     * @param IUserRepository $userRepository
     * @param IScheduleRepository $scheduleRepository
     */
    public function __construct(
        IReservationValidationService $serviceToDecorate,
        OffHoursSchedule $regularHours,
        array $allowedGroups,
        string $message,
        IUserRepository $userRepository,
        IScheduleRepository $scheduleRepository
    ) {
        $this->serviceToDecorate = $serviceToDecorate;
        $this->regularHours = $regularHours;
        $this->allowedGroups = $allowedGroups;
        $this->message = $message;
        $this->userRepository = $userRepository;
        $this->scheduleRepository = $scheduleRepository;
    }

    public function Validate($series, $retryParameters = null)
    {
        $result = $this->serviceToDecorate->Validate($series, $retryParameters);

        // don't bother validating this rule if others have failed
        if (!$result->CanBeSaved()) {
            return $result;
        }

        return $this->EvaluateCustomRule($series);
    }

    private function EvaluateCustomRule($series)
    {
        $schedule = $this->scheduleRepository->LoadById($series->ScheduleId());
        $timezone = $schedule ? $schedule->GetTimezone() : Configuration::Instance()->GetDefaultTimezone();

        $isOutsideRegularHours = false;
        foreach ($series->Instances() as $instance) {
            if (!$this->regularHours->Contains($instance->StartDate(), $instance->EndDate(), $timezone)) {
                $isOutsideRegularHours = true;
                break;
            }
        }

        if (!$isOutsideRegularHours || $this->IsInAllowedGroup($series->UserId())) {
            return new ReservationValidationResult();
        }

        Log::Debug('OffHours: reservation outside regular hours by a user not in an allowed group. UserId=%s', $series->UserId());

        return new ReservationValidationResult(false, [$this->message]);
    }

    private function IsInAllowedGroup($userId)
    {
        $user = $this->userRepository->LoadById($userId);
        if ($user === null) {
            return false;
        }

        foreach ($user->Groups() as $group) {
            if (in_array(strtolower($group->GroupName), $this->allowedGroups, true)) {
                return true;
            }
        }

        return false;
    }
}
