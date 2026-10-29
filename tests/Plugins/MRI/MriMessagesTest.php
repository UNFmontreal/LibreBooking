<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LibreBooking\Mri\MriMessages;
use LogicException;
use ReflectionClass;
use TestBase;

class MriMessagesTest extends TestBase
{
    public function testEveryMessageHasAnEnglishText()
    {
        $english = require ROOT_DIR . 'plugins/MRI/lang/en_us.php';

        foreach ((new ReflectionClass(MriMessages::class))->getReflectionConstants() as $constant) {
            $this->assertArrayHasKey($constant->getValue(), $english, $constant->getName());
        }
    }

    public function testTextReturnsTheMessageForItsKey()
    {
        $this->assertSame(
            'ERROR in REGEX file - contact Support!',
            MriMessages::text(MriMessages::REGEX_FILE_ERROR)
        );
    }

    public function testUnknownKeyIsAnError()
    {
        $this->expectException(LogicException::class);

        MriMessages::text('message.does_not_exist');
    }
}
