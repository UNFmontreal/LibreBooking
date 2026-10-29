<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI;

use LimesurveyScreeningPreConfigKeys;
use TestBase;

require_once(ROOT_DIR . 'plugins/PreReservation/LimesurveyScreeningPre/LimesurveyScreeningPreConfigKeys.php');

class LimesurveyScreeningPreConfigKeysTest extends TestBase
{
    public function testEverySettingHasALabelAndADescriptionFromTheLanguageFile()
    {
        foreach (LimesurveyScreeningPreConfigKeys::all() as $key) {
            $this->assertNotEmpty($key->label, $key->key);
            $this->assertNotEmpty($key->description, $key->key);
        }
    }

    public function testSettingsAreFoundByKeyWithTheirLabel()
    {
        $key = LimesurveyScreeningPreConfigKeys::findByKey('limesurveyscreeningpre.ls.base.url');

        $this->assertSame('LimeSurvey Base URL', $key?->label);
    }

    public function testSettingsAreInOneSection()
    {
        foreach (LimesurveyScreeningPreConfigKeys::all() as $key) {
            $this->assertSame('limesurveyscreeningpre', $key->section, $key->key);
        }
    }

    /**
     * The admin configuration page loads this file alone, so it must load the classes it uses by itself.
     */
    public function testKeysFileLoadsItsOwnDependenciesInACleanProcess()
    {
        $script = sprintf(
            'define("ROOT_DIR", %s); require_once ROOT_DIR . "plugins/PreReservation/LimesurveyScreeningPre/LimesurveyScreeningPreConfigKeys.php"; echo class_exists("LibreBooking\\Mri\\MriMessages") ? "loaded" : "missing";',
            var_export(ROOT_DIR, true)
        );

        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1');

        $this->assertSame('loaded', trim((string) $output));
    }
}
