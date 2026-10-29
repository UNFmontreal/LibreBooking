<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'lib/Application/Reservation/Validation/namespace.php');
require_once(ROOT_DIR . 'plugins/PostReservation/MRIPostRes/MRIPostRes.php');

/**
 * Loads the whole plugin file chain, so a missing require or a bad class declaration fails here.
 */
class MRIPostResLoadTest extends TestBase
{
    public function testPluginClassesAreDeclared()
    {
        $this->assertTrue(class_exists(MRIPostRes::class));
        $this->assertTrue(class_exists(MRIPostResNotifier::class) || class_exists('MRIPostResNotifier'));
        $this->assertTrue(is_subclass_of(PostReservationCreated::class, MRIPostResNotifier::class));
        $this->assertTrue(is_subclass_of(PostReservationUpdate::class, MRIPostResNotifier::class));
        $this->assertTrue(is_subclass_of(PostReservationDeleted::class, MRIPostResNotifier::class));
    }
}
