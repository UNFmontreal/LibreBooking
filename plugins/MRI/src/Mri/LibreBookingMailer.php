<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * Sends mail through LibreBooking's email service, which applies the configured sender and transport.
 */
class LibreBookingMailer
{
    public function send(string $toAddress, string $toName, string $subject, string $body): void
    {
        $from = new \EmailAddress(
            (string) \Configuration::Instance()->GetKey(\ConfigKeys::EMAIL_DEFAULT_FROM_ADDRESS),
            (string) \Configuration::Instance()->GetKey(\ConfigKeys::EMAIL_DEFAULT_FROM_NAME)
        );

        \ServiceLocator::GetEmailService()->Send(
            new MriEmailMessage($from, new \EmailAddress($toAddress, $toName), $subject, $body)
        );
    }
}
