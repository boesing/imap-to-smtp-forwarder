<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Forwarder;

use Boesing\ImapToSmtpForwarder\Configuration\ForwardConfigurationInterface;
use Boesing\ImapToSmtpForwarder\Configuration\ImapConfigurationInterface;
use Boesing\ImapToSmtpForwarder\Configuration\SmtpConfigurationInterface;
use Boesing\ImapToSmtpForwarder\Imap\Imap;
use Boesing\ImapToSmtpForwarder\Imap\ImapInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\TransportInterface;

use function sprintf;

final class ForwarderFactory implements ForwarderFactoryInterface
{
    /**
     * @var array<non-empty-string,ImapInterface>
     */
    private array $imap = [];

    /**
     * @var array<non-empty-string,TransportInterface>
     */
    private array $smtp = [];

    public function __construct(
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function createForwarderFromConfiguration(
        ForwardConfigurationInterface $forwardConfiguration,
    ): ForwarderInterface {
        $imap = $this->createImap($forwardConfiguration->getImapConfiguration());
        $smtp = $this->createSmtp($forwardConfiguration->getSmtpConfiguration());

        return new Forwarder(
            $imap,
            $forwardConfiguration->getTemplate(),
            $forwardConfiguration->getForwardDestinations(),
            $forwardConfiguration->getSmtpConfiguration()->getSender(),
            new Mailer($smtp),
            $forwardConfiguration->getActionAfterForward(),
            $forwardConfiguration->getInboxToWatch(),
        );
    }

    private function createImap(
        ImapConfigurationInterface $configuration,
    ): ImapInterface {
        if (isset($this->imap[$configuration->getIdentifier()])) {
            return $this->imap[$configuration->getIdentifier()];
        }

        $imap                                        = new Imap(
            $configuration,
            $this->streamFactory,
        );
        $this->imap[$configuration->getIdentifier()] = $imap;

        return $imap;
    }

    private function createSmtp(
        SmtpConfigurationInterface $configuration,
    ): TransportInterface {
        if (isset($this->smtp[$configuration->getIdentifier()])) {
            return $this->smtp[$configuration->getIdentifier()];
        }

        $factory = new EsmtpTransportFactory();

        $smtp = $factory->create(Dsn::fromString(
            sprintf(
                'tcp://%s:%s@%s:%d',
                $configuration->getUsername(),
                $configuration->getPassword(),
                $configuration->getHostname(),
                $configuration->getPort(),
            ),
        ));

        $this->smtp[$configuration->getIdentifier()] = $smtp;

        return $smtp;
    }
}
