<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Boesing\ImapToSmtpForwarder\AddressTransfer;
use Override;

final readonly class SmtpConfiguration implements SmtpConfigurationInterface
{
    /**
     * @param non-empty-string $identifier
     * @param non-empty-string $username
     * @param non-empty-string $password
     * @param non-empty-string $hostname
     * @param int<0,65535>     $port
     */
    public function __construct(
        private string $identifier,
        private AddressTransfer $sender,
        private string $username,
        private string $password,
        private string $hostname,
        private int $port,
    ) {
    }

    #[Override]
    public function getSender(): AddressTransfer
    {
        return $this->sender;
    }

    #[Override]
    public function getUsername(): string
    {
        return $this->username;
    }

    #[Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    #[Override]
    public function getHostname(): string
    {
        return $this->hostname;
    }

    #[Override]
    public function getPort(): int
    {
        return $this->port;
    }

    #[Override]
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
