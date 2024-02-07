<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Imap;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Psr\Http\Message\StreamInterface;

interface MessageInterface
{
    public function getMessageId(): int;

    public function getRawMessage(): StreamInterface;

    public function getMailFromAddress(): AddressTransfer;

    public function getSubject(): string;

    public function getMailToAddress(): AddressTransfer;
}
