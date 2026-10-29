<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\MriReservationContext;
use LibreBooking\Mri\ScreeningState;
use stdClass;
use TestBase;

class MriReservationContextTest extends TestBase
{
    public function testContextAttachedToASeriesIsFoundForThatSeriesOnly()
    {
        $series = new stdClass();
        $other = new stdClass();
        $context = $this->context();

        MriReservationContext::attach($series, $context);

        $this->assertSame($context, MriReservationContext::for($series));
        $this->assertNull(MriReservationContext::for($other));
    }

    public function testSeriesIsNotModifiedByAttachingAContext()
    {
        $series = new stdClass();

        MriReservationContext::attach($series, $this->context());

        $this->assertSame([], get_object_vars($series));
    }

    private function context(): MriReservationContext
    {
        return new MriReservationContext(new ScreeningState('REF-1', false, false, false, false, 'ANT_001', null), true);
    }
}
