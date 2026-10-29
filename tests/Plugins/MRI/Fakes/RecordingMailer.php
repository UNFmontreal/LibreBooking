<?php

declare(strict_types=1);

namespace LibreBooking\Tests\Plugins\MRI\Fakes;

use LibreBooking\Mri\LibreBookingMailer;

class RecordingMailer extends LibreBookingMailer
{
    /**
     * @var list<array{to: string, toName: string, subject: string, body: string}>
     */
    public array $sent = [];

    public function send(string $toAddress, string $toName, string $subject, string $body): void
    {
        $this->sent[] = ['to' => $toAddress, 'toName' => $toName, 'subject' => $subject, 'body' => $body];
    }
}
