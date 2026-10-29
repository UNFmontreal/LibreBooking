<?php

declare(strict_types=1);

require_once(ROOT_DIR . 'plugins/PostReservation/MRIPostRes/MriLimesurvey.php');

class MriLimesurveyTest extends TestBase
{
    public function testRecognisesDuplicateTokenError()
    {
        $response = [['token' => 'REF-1', 'errors' => ['token' => ['Token "REF-1" has already been taken.']]]];

        $this->assertTrue(MriLimesurvey::isDuplicateTokenError($response, 'REF-1'));
    }

    public function testOtherTokenErrorsAreNotDuplicates()
    {
        $response = [['token' => 'REF-2', 'errors' => ['token' => ['Token "REF-2" has already been taken.']]]];

        $this->assertFalse(MriLimesurvey::isDuplicateTokenError($response, 'REF-1'));
    }

    public function testSuccessfulResponseIsNotADuplicate()
    {
        $this->assertFalse(MriLimesurvey::isDuplicateTokenError([['token' => 'REF-1', 'tid' => '3']], 'REF-1'));
    }

    public function testNonArrayResponseIsNotADuplicate()
    {
        $this->assertFalse(MriLimesurvey::isDuplicateTokenError('error string', 'REF-1'));
        $this->assertFalse(MriLimesurvey::isDuplicateTokenError(null, 'REF-1'));
    }
}
