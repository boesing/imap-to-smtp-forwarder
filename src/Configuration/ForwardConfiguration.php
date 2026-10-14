<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Override;
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

    #[Override]
    public function getImapConfiguration(): ImapConfigurationInterface
    {
        return $this->imapConfiguration;
    }

    #[Override]
    public function getSmtpConfiguration(): SmtpConfigurationInterface
    {
        return $this->smtpConfiguration;
    }

    #[Override]
    public function getForwardDestinations(): array
    {
        return $this->forwardDestinations;
    }

    #[Override]
    public function getTemplate(): StreamInterface
    {
        return $this->template;
    }

    #[Override]
    public function getActionAfterForward(): DeleteActionInterface|MoveActionInterface
    {
        return $this->action;
    }

    #[Override]
    public function getInboxToWatch(): string
    {
        return $this->inboxToWatch;
    }
}
