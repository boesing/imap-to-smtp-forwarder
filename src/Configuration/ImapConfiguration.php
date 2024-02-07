<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use SensitiveParameter;

final class ImapConfiguration implements ImapConfigurationInterface
{
    /**
     * @param non-empty-string $identifier
     * @param non-empty-string $hostname
     * @param int<0,65535>     $port
     * @param non-empty-string $username
     * @param non-empty-string $password
     */
    public function __construct(
        private readonly string $identifier,
        private readonly string $hostname,
        private readonly int $port,
        private readonly string $username,
        #[SensitiveParameter]
        private readonly string $password,
    ) {
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
