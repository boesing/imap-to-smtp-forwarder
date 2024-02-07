<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

final class JsonConfigurationSmtp
{
    /**
     * @param non-empty-string $hostname
     * @param non-empty-string $username
     * @param non-empty-string $password
     * @param int<0,65535>     $port
     */
    public function __construct(
        public readonly string $hostname,
        public readonly string $username,
        public readonly string $password,
        public readonly int $port = 25,
    ) {
    }
}
