<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\ResourceNames;
use TestBase;

class ResourceNamesTest extends TestBase
{
    public function testSplitsCommaSeparatedNames()
    {
        $this->assertSame(['IRM 3T', 'EEG'], ResourceNames::parse('IRM 3T,EEG'));
    }

    public function testIgnoresSpacesAroundSeparators()
    {
        $this->assertSame(['IRM 3T', 'EEG', 'MEG'], ResourceNames::parse('IRM 3T , EEG, MEG'));
    }

    public function testEmptySettingMonitorsNothing()
    {
        $this->assertSame([], ResourceNames::parse(''));
        $this->assertSame([], ResourceNames::parse(' , '));
    }

    public function testFindsTheIdsOfTheNamedResources()
    {
        $ids = ResourceNames::idsOf(['IRM 3T', 'EEG'], fn (string $name): object => $this->resource(['IRM 3T' => 7, 'EEG' => 9][$name]));

        $this->assertSame([7, 9], $ids);
    }

    public function testNameOfNoResourceIsSkipped()
    {
        // the repository answers a resource without id when no resource has the name
        $ids = ResourceNames::idsOf(['IRM 3T', 'Typo'], fn (string $name): object => $this->resource($name === 'IRM 3T' ? 7 : null));

        $this->assertSame([7], $ids);
    }

    public function testNoNamedResourceGivesNoIds()
    {
        $this->assertSame([], ResourceNames::idsOf(['Typo'], fn (string $name): object => $this->resource(null)));
    }

    private function resource(?int $id): object
    {
        return new class ($id) {
            public function __construct(private ?int $id)
            {
            }

            public function GetId(): ?int
            {
                return $this->id;
            }
        };
    }
}
