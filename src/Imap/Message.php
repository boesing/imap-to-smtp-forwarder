<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Imap;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Psr\Http\Message\StreamInterface;

final class Message implements MessageInterface
{
    public function __construct(
        private readonly int $messageId,
        private readonly StreamInterface $rawMessage,
        private readonly AddressTransfer $from,
        private readonly AddressTransfer $to,
        private readonly string $subject,
    ) {
    }

    public function getMessageId(): int
    {
        return $this->messageId;
    }

    public function getRawMessage(): StreamInterface
    {
        return $this->rawMessage;
    }

    public function getMailFromAddress(): AddressTransfer
    {
        return $this->from;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getMailToAddress(): AddressTransfer
    {
        return $this->to;
    }
}
