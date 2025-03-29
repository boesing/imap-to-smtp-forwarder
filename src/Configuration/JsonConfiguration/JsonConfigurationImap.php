<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

use SensitiveParameter;

final readonly class JsonConfigurationImap
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
        #[SensitiveParameter]
        public string $password,
        public int $port = 993,
    ) {
    }
}
