<?php

declare(strict_types=1);

namespace LibreBooking\Mri;

/**
 * A plain text-and-HTML message to one recipient, for LibreBooking's email service.
 */
final class MriEmailMessage implements \IEmailMessage
{
    /**
     * @param \EmailAddress $from default sender; the email service replaces it with the configured sender
     */
    public function __construct(
        private readonly \EmailAddress $from,
        private readonly \EmailAddress $to,
        private readonly string $subject,
        private readonly string $body,
    ) {
    }

    public function Charset()
    {
        return 'UTF-8';
    }

    public function To()
    {
        return [$this->to];
    }

    public function From()
    {
        return $this->from;
    }

    public function CC()
    {
        return [];
    }

    public function BCC()
    {
        return [];
    }

    public function Subject()
    {
        return $this->subject;
    }

    public function Body()
    {
        return $this->body;
    }

    public function ReplyTo()
    {
        return $this->from;
    }

    public function AddStringAttachment($contents, $fileName, ?string $mimeType = null)
    {
        throw new \LogicException('MRI messages do not take attachments');
    }

    public function HasStringAttachment()
    {
        return false;
    }

    public function AttachmentContents()
    {
        return null;
    }

    public function AttachmentFileName()
    {
        return null;
    }

    public function AttachmentMimeType(): ?string
    {
        return null;
    }
}
