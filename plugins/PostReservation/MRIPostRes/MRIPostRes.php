<?php

/* This file should be installed in /plugins/PostReservation/MRIPostRes
**************************************************************
Copyright 2021 APPS-N-ADD-ONS.COM

Permission is hereby granted, free of charge, to any person obtaining a copy of this software
and associated documentation files (the "Software"), to deal in the Software without restriction,
including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies
or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
**************************************************************
       this package may contain 'snippets' from various
 websites/vendors with terms/conditions under other use license
            see individual license terms as referenced
***************************************************************
     this package is designed to work with Booked Scheduler
************************** NO USER CONFIGURATION ITEMS ********************************
*/

// Settings come from the MRI pre-reservation plugin; the context it attaches is LibreBooking\Mri\MriReservationContext.
require_once(dirname(__FILE__) . '/../../MRI/autoload.php');
require_once(dirname(__FILE__) . '/../../PreReservation/LimesurveyScreeningPre/LimesurveyScreeningPreConfig.php');

require_once(dirname(__FILE__) . '/MriCancellationWindow.php');
require_once(dirname(__FILE__) . '/MriLimesurvey.php');
require_once(dirname(__FILE__) . '/MriPostResSettings.php');
require_once(dirname(__FILE__) . '/MriAttributeValues.php');
require_once(dirname(__FILE__) . '/MriSeriesStatus.php');
require_once(dirname(__FILE__) . '/MriOperator.php');
require_once(dirname(__FILE__) . '/MRIPostResNotifier.php');
require_once(dirname(__FILE__) . '/PostReservationCreated.php');
require_once(dirname(__FILE__) . '/PostReservationUpdate.php');
require_once(dirname(__FILE__) . '/PostReservationDeleted.php');

/**
 * Synchronises MRI screening participants, logbook entries and reservation links after reservations change.
 */
class MRIPostRes implements IPostReservationFactory
{
    /**
     * @var PostReservationFactory
     */
    private $factoryToDecorate;

    public function __construct(PostReservationFactory $factoryToDecorate)
    {
        $this->factoryToDecorate = $factoryToDecorate;
        LimesurveyScreeningPreConfig::Register();
        Log::Debug('MRIPostRes is ready');
    }

    /**
     * @param UserSession $userSession
     * @return IReservationNotificationService
     */
    public function CreatePostAddService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePostAddService($userSession);
        return new PostReservationCreated($base);
    }

    /**
     * @param UserSession $userSession
     * @return IReservationNotificationService
     */
    public function CreatePostUpdateService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePostUpdateService($userSession);
        return new PostReservationUpdate($base);
    }

    /**
     * @param UserSession $userSession
     * @return IReservationNotificationService
     */
    public function CreatePostDeleteService(UserSession $userSession)
    {
        $base = $this->factoryToDecorate->CreatePostDeleteService($userSession);
        return new PostReservationDeleted($base);
    }

    /**
     * Approval does not add MRI behaviour: the add service of the base factory is used.
     *
     * @param UserSession $userSession
     * @return IReservationNotificationService
     */
    public function CreatePostApproveService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePostAddService($userSession);
    }

    /**
     * @param UserSession $userSession
     * @return IReservationNotificationService
     */
    public function CreatePostCheckinService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePostCheckinService($userSession);
    }

    /**
     * @param UserSession $userSession
     * @return IReservationNotificationService
     */
    public function CreatePostCheckoutService(UserSession $userSession)
    {
        return $this->factoryToDecorate->CreatePostCheckoutService($userSession);
    }
}
