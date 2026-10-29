<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * Finds the ids of reservation custom attributes from their labels.
 *
 * The ids differ between installations, but the labels shown to the users are what an administrator knows, so the MRI
 * settings name the attributes by label. Labels are compared without regard to case and surrounding spaces.
 */
final class CustomAttributes
{
    /**
     * @var callable(): iterable<object> the reservation custom attributes, each with Id() and Label()
     */
    private $attributes;

    /**
     * @var array<string, int>|null attribute id by normalized label
     */
    private ?array $idByLabel = null;

    /**
     * @param (callable(): iterable<object>)|null $attributes defaults to the reservation attributes of LibreBooking
     */
    public function __construct(?callable $attributes = null)
    {
        $this->attributes = $attributes
            ?? fn (): iterable => (new \AttributeRepository())->GetByCategory(\CustomAttributeCategory::RESERVATION);
    }

    /**
     * The id of the attribute with the label, or 0 when no label is given (the attribute is not used) or no attribute
     * has it (the problem is logged). Attribute id 0 never holds a value, so the feature that needs it stays inactive.
     */
    public function idOf(string $label): int
    {
        $key = self::normalize($label);
        if ($key === '') {
            return 0;
        }

        $this->idByLabel ??= $this->index();
        if (!isset($this->idByLabel[$key])) {
            \Log::Error('No reservation custom attribute is labelled "%s"', $label);
            return 0;
        }

        return $this->idByLabel[$key];
    }

    /**
     * @return array<string, int>
     */
    private function index(): array
    {
        $index = [];
        foreach (($this->attributes)() as $attribute) {
            $index[self::normalize((string) $attribute->Label())] ??= (int) $attribute->Id();
        }

        return $index;
    }

    private static function normalize(string $label): string
    {
        return mb_strtolower(trim($label));
    }
}
