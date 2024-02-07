<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Psr\Http\Message\StreamInterface;

final class ForwardConfiguration implements ForwardConfigurationInterface
{
    /**
     * @param non-empty-list<AddressTransfer> $forwardDestinations
     * @param non-empty-string                $inboxToWatch
     */
    public function __construct(
        private readonly ImapConfigurationInterface $imapConfiguration,
        private readonly SmtpConfigurationInterface $smtpConfiguration,
        private readonly array $forwardDestinations,
        private readonly StreamInterface $template,
        private readonly DeleteActionInterface|MoveActionInterface $action,
        private readonly string $inboxToWatch,
    ) {
    }

    public function getImapConfiguration(): ImapConfigurationInterface
    {
        return $this->imapConfiguration;
    }

    public function getSmtpConfiguration(): SmtpConfigurationInterface
    {
        return $this->smtpConfiguration;
    }

    public function getForwardDestinations(): array
    {
        return $this->forwardDestinations;
    }

    public function getTemplate(): StreamInterface
    {
        return $this->template;
    }

    public function getActionAfterForward(): DeleteActionInterface|MoveActionInterface
    {
        return $this->action;
    }

    public function getInboxToWatch(): string
    {
        return $this->inboxToWatch;
    }
}
