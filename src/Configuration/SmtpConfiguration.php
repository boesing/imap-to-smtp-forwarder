<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Boesing\ImapToSmtpForwarder\AddressTransfer;

final class SmtpConfiguration implements SmtpConfigurationInterface
{
    /**
     * @param non-empty-string $identifier
     * @param non-empty-string $username
     * @param non-empty-string $password
     * @param non-empty-string $hostname
     * @param int<0,65535>     $port
     */
    public function __construct(
        private readonly string $identifier,
        private readonly AddressTransfer $sender,
        private readonly string $username,
        private readonly string $password,
        private readonly string $hostname,
        private readonly int $port,
    ) {
    }

    public function getSender(): AddressTransfer
    {
        return $this->sender;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
