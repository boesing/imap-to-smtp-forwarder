<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Override;
use SensitiveParameter;

final readonly class ImapConfiguration implements ImapConfigurationInterface
{
    /**
     * @param non-empty-string $identifier
     * @param non-empty-string $hostname
     * @param int<0,65535>     $port
     * @param non-empty-string $username
     * @param non-empty-string $password
     */
    public function __construct(
        private string $identifier,
        private string $hostname,
        private int $port,
        private string $username,
        #[SensitiveParameter]
        private string $password,
    ) {
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
    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
