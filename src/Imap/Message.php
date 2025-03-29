<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Imap;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Override;
use Psr\Http\Message\StreamInterface;

final readonly class Message implements MessageInterface
{
    public function __construct(
        private int $messageId,
        private StreamInterface $rawMessage,
        private AddressTransfer $from,
        private AddressTransfer $to,
        private string $subject,
    ) {
    }

    #[Override]
    public function getMessageId(): int
    {
        return $this->messageId;
    }

    #[Override]
    public function getRawMessage(): StreamInterface
    {
        return $this->rawMessage;
    }

    #[Override]
    public function getMailFromAddress(): AddressTransfer
    {
        return $this->from;
    }

    #[Override]
    public function getSubject(): string
    {
        return $this->subject;
    }

    #[Override]
    public function getMailToAddress(): AddressTransfer
    {
        return $this->to;
    }
}
