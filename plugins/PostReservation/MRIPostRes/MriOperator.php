<?php

/**
 * Keeps the MRI operator of a reservation instance in the reservation_users table (user level 2).
 *
 * There is no LibreBooking command for this table yet, so the queries stay here, with integer arguments only.
 */
final class MriOperator
{
    private const LEVEL_OWNER = 1;
    private const LEVEL_MRI_OPERATOR = 2;

    /**
     * Synchronises the operator of an existing instance (update path).
     */
    public static function syncForInstance(int $operatorId, int $reservationId): void
    {
        $operators = self::userIdsAtLevel($reservationId, self::LEVEL_MRI_OPERATOR);
        if (count($operators) > 1 || (count($operators) === 1 && $operators[0] != $operatorId)) {
            // Legacy behaviour: the removal is by user id, so it applies to every reservation of that user.
            foreach ($operators as $userId) {
                self::removeOperator($userId);
            }
        }

        self::addOperator($operatorId, $reservationId);
    }

    /**
     * Records the operator of a newly created instance (create path).
     */
    public static function syncForNewInstance(int $operatorId, int $reservationId): void
    {
        self::addOperator($operatorId, $reservationId);
    }

    private static function addOperator(int $operatorId, int $reservationId): void
    {
        if ($operatorId === 0) {
            return;
        }

        $owner = self::userIdsAtLevel($reservationId, self::LEVEL_OWNER)[0] ?? null;
        if ($owner == $operatorId) {
            return;
        }

        ServiceLocator::GetDatabase()->Query(new AdHocCommand(
            "INSERT INTO reservation_users (user_id, reservation_instance_id, reservation_user_level) VALUES ($operatorId, $reservationId, " . self::LEVEL_MRI_OPERATOR . ')'
        ));
    }

    private static function removeOperator($userId): void
    {
        $level = self::LEVEL_MRI_OPERATOR;
        ServiceLocator::GetDatabase()->Query(new AdHocCommand(
            'DELETE FROM reservation_users WHERE user_id = ' . (int) $userId . " and reservation_user_level = $level"
        ));
    }

    /**
     * @return array user ids holding the given level on the reservation instance
     */
    private static function userIdsAtLevel(int $reservationId, int $level): array
    {
        $reader = ServiceLocator::GetDatabase()->Query(new AdHocCommand(
            "select user_id from reservation_users where reservation_instance_id = $reservationId and reservation_user_level = $level"
        ));

        $userIds = [];
        while ($row = $reader->GetRow()) {
            $userIds[] = $row['user_id'];
        }
        $reader->Free();

        return $userIds;
    }
}
