<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/Validation/namespace.php');
require_once(ROOT_DIR . 'plugins/PreReservation/OffHours/OffHoursSchedule.php');
require_once(ROOT_DIR . 'plugins/PreReservation/OffHours/OffHoursValidation.php');

class OffHoursValidationTest extends TestBase
{
    private const MESSAGE = 'Only authorized groups can book outside regular hours.';

    // 2026-10-26 is a Monday
    private const OUTSIDE_START = '2026-10-26 19:00:00';
    private const OUTSIDE_END = '2026-10-26 20:00:00';
    private const INSIDE_START = '2026-10-26 09:00:00';
    private const INSIDE_END = '2026-10-26 10:00:00';

    public function testReservationInsideRegularHoursIsAllowedWithoutGroupCheck()
    {
        $userRepository = $this->createMock(IUserRepository::class);
        $userRepository->expects($this->never())->method('LoadById');

        $validation = $this->validation(true, [], $userRepository);

        $result = $validation->Validate($this->series(self::INSIDE_START, self::INSIDE_END));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testReservationOutsideRegularHoursIsAllowedForMemberOfAllowedGroup()
    {
        $validation = $this->validation(true, ['staff'], $this->userWithGroups(['Staff', 'Other']));

        $result = $validation->Validate($this->series(self::OUTSIDE_START, self::OUTSIDE_END));

        $this->assertTrue($result->CanBeSaved());
    }

    public function testReservationOutsideRegularHoursIsRejectedForOtherUsers()
    {
        $validation = $this->validation(true, ['staff'], $this->userWithGroups(['Students']));

        $result = $validation->Validate($this->series(self::OUTSIDE_START, self::OUTSIDE_END));

        $this->assertFalse($result->CanBeSaved());
        $this->assertContains(self::MESSAGE, $result->GetErrors());
    }

    public function testReservationOutsideRegularHoursIsRejectedWhenUserDoesNotExist()
    {
        $validation = $this->validation(true, ['staff'], $this->userRepositoryReturning(null));

        $result = $validation->Validate($this->series(self::OUTSIDE_START, self::OUTSIDE_END));

        $this->assertFalse($result->CanBeSaved());
    }

    public function testReservationWithAnyInstanceOutsideRegularHoursIsRejected()
    {
        $validation = $this->validation(true, [], $this->userWithGroups(['Students']));

        $result = $validation->Validate($this->series(self::INSIDE_START, self::INSIDE_END, self::OUTSIDE_START, self::OUTSIDE_END));

        $this->assertFalse($result->CanBeSaved());
    }

    public function testBaseValidationFailureIsReturnedUnchanged()
    {
        $base = $this->createMock(IReservationValidationService::class);
        $base->method('Validate')->willReturn(new ReservationValidationResult(false, ['base failure']));
        $validation = new OffHoursValidation(
            $base,
            new OffHoursSchedule('Mon-Fri:08:00-18:00'),
            [],
            self::MESSAGE,
            $this->userRepositoryReturning(null),
            $this->scheduleRepository()
        );

        $result = $validation->Validate($this->series(self::OUTSIDE_START, self::OUTSIDE_END));

        $this->assertFalse($result->CanBeSaved());
        $this->assertContains('base failure', $result->GetErrors());
    }

    /**
     * @param string[] $allowedGroups lowercase
     */
    private function validation(bool $baseSaves, array $allowedGroups, $userRepository): OffHoursValidation
    {
        $base = $this->createMock(IReservationValidationService::class);
        $base->method('Validate')->willReturn(new ReservationValidationResult($baseSaves));

        return new OffHoursValidation(
            $base,
            new OffHoursSchedule('Mon-Fri:08:00-18:00'),
            $allowedGroups,
            self::MESSAGE,
            $userRepository,
            $this->scheduleRepository()
        );
    }

    private function scheduleRepository(): IScheduleRepository
    {
        $schedule = $this->createMock(Schedule::class);
        $schedule->method('GetTimezone')->willReturn('UTC');

        $scheduleRepository = $this->createMock(IScheduleRepository::class);
        $scheduleRepository->method('LoadById')->willReturn($schedule);

        return $scheduleRepository;
    }

    /**
     * @param string[] $groupNames
     */
    private function userWithGroups(array $groupNames): IUserRepository
    {
        $groups = [];
        foreach ($groupNames as $index => $name) {
            $groups[] = new UserGroup($index + 1, $name);
        }

        $user = $this->createMock(User::class);
        $user->method('Groups')->willReturn($groups);

        return $this->userRepositoryReturning($user);
    }

    private function userRepositoryReturning($user): IUserRepository
    {
        $userRepository = $this->createMock(IUserRepository::class);
        $userRepository->method('LoadById')->willReturn($user);

        return $userRepository;
    }

    /**
     * Builds a series whose instances are given as start/end pairs (UTC, schedule timezone is UTC)
     */
    private function series(string $start, string $end, ?string $secondStart = null, ?string $secondEnd = null): ReservationSeries
    {
        $instances = [$this->reservation($start, $end)];
        if ($secondStart !== null) {
            $instances[] = $this->reservation($secondStart, $secondEnd);
        }

        $series = $this->createMock(ReservationSeries::class);
        $series->method('ScheduleId')->willReturn(1);
        $series->method('UserId')->willReturn(42);
        $series->method('Instances')->willReturn($instances);

        return $series;
    }

    private function reservation(string $start, string $end): Reservation
    {
        $reservation = $this->createMock(Reservation::class);
        $reservation->method('StartDate')->willReturn(new Date($start, 'UTC'));
        $reservation->method('EndDate')->willReturn(new Date($end, 'UTC'));

        return $reservation;
    }
}
