<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Psr\Http\Message\StreamInterface;

interface ForwardConfigurationInterface
{
    public function getImapConfiguration(): ImapConfigurationInterface;

    public function getSmtpConfiguration(): SmtpConfigurationInterface;

    /**
     * @return non-empty-list<AddressTransfer>
     */
    public function getForwardDestinations(): array;

    public function getTemplate(): StreamInterface;

    public function getActionAfterForward(): DeleteActionInterface|MoveActionInterface;

    /**
     * @return non-empty-string
     */
    public function getInboxToWatch(): string;
}
