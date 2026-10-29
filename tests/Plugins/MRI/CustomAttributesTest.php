<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\CustomAttributes;
use TestBase;

class CustomAttributesTest extends TestBase
{
    public function testFindsTheIdOfALabel()
    {
        $this->assertSame(12, $this->attributes()->idOf('Research Assistant/ Assistant de Recherche'));
        $this->assertSame(1, $this->attributes()->idOf('session name (only alphanumeric dash underscore)'));
    }

    public function testLabelsAreComparedWithoutCaseOrSurroundingSpaces()
    {
        $this->assertSame(14, $this->attributes()->idOf('  phantom/FANTÔME '));
    }

    public function testEmptyLabelMeansTheAttributeIsNotUsed()
    {
        $this->assertSame(0, $this->attributes()->idOf(''));
        $this->assertSame(0, $this->attributes()->idOf('   '));
    }

    public function testUnknownLabelGivesZero()
    {
        $this->assertSame(0, $this->attributes()->idOf('No such attribute'));
    }

    public function testFirstAttributeWinsWhenALabelIsRepeated()
    {
        $attributes = new CustomAttributes(fn () => [$this->attribute(3, 'Same'), $this->attribute(4, 'same')]);

        $this->assertSame(3, $attributes->idOf('Same'));
    }

    public function testAttributesAreLoadedOnce()
    {
        $loads = 0;
        $attributes = new CustomAttributes(function () use (&$loads): array {
            $loads++;
            return [$this->attribute(1, 'A'), $this->attribute(2, 'B')];
        });

        $attributes->idOf('A');
        $attributes->idOf('B');
        $attributes->idOf('missing');

        $this->assertSame(1, $loads);
    }

    private function attributes(): CustomAttributes
    {
        return new CustomAttributes(fn () => [
            $this->attribute(1, 'session name (only alphanumeric dash underscore)'),
            $this->attribute(12, 'Research Assistant/ Assistant de Recherche'),
            $this->attribute(14, 'Phantom/Fantôme'),
        ]);
    }

    private function attribute(int $id, string $label): object
    {
        return new class ($id, $label) {
            public function __construct(private int $id, private string $label)
            {
            }

            public function Id(): int
            {
                return $this->id;
            }

            public function Label(): string
            {
                return $this->label;
            }
        };
    }
}
