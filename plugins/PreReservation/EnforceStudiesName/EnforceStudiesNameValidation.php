<?php

use LibreBooking\Studies\StudyCatalog;

/**
 * Rejects reservations whose configured custom attribute values match no studies regex.
 * Blank values are skipped; required attributes are enforced by the core custom attribute rules.
 */
class EnforceStudiesNameValidation implements IReservationValidationService
{
    /**
     * @var IReservationValidationService
     */
    private $serviceToDecorate;

    /**
     * @var int[]
     */
    private $attributeIds;

    /**
     * @var callable(): StudyCatalog loads the studies when they are first needed
     */
    private $studies;

    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $unavailableMessage;

    /**
     * @param int[] $attributeIds custom attribute IDs to check
     */
    public function __construct(
        IReservationValidationService $serviceToDecorate,
        array $attributeIds,
        callable $studies,
        string $message,
        string $unavailableMessage
    ) {
        $this->serviceToDecorate = $serviceToDecorate;
        $this->attributeIds = $attributeIds;
        $this->studies = $studies;
        $this->message = $message;
        $this->unavailableMessage = $unavailableMessage;
    }

    public function Validate($series, $retryParameters = null)
    {
        $result = $this->serviceToDecorate->Validate($series, $retryParameters);

        // don't bother validating this rule if others have failed
        if (!$result->CanBeSaved()) {
            return $result;
        }

        $values = $this->GetValuesToCheck($series);
        if (empty($values)) {
            return $result;
        }

        // only download the studies once a checked value is actually present
        $studies = ($this->studies)();
        if (!$studies->isAvailable()) {
            return new ReservationValidationResult(false, [$this->unavailableMessage]);
        }

        foreach ($values as $value) {
            if (!$studies->matches($value)) {
                Log::Debug('EnforceStudiesName rejected value. AttributeValue=%s', $value);
                return new ReservationValidationResult(false, [$this->message]);
            }
        }

        return $result;
    }

    /**
     * @return string[]
     */
    private function GetValuesToCheck($series): array
    {
        $values = [];
        foreach ($this->attributeIds as $attributeId) {
            $value = $series->GetAttributeValue($attributeId);
            if ($value === null || trim((string) $value) === '') {
                continue;
            }
            $values[] = (string) $value;
        }

        return $values;
    }
}
