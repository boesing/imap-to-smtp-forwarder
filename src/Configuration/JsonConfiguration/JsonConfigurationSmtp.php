<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

final readonly class JsonConfigurationSmtp
{
    /**
     * @param non-empty-string $hostname
     * @param non-empty-string $username
     * @param non-empty-string $password
     * @param int<0,65535>     $port
     */
    public function __construct(
        public string $hostname,
        public string $username,
        public string $password,
        public int $port = 25,
    ) {
    }
}
