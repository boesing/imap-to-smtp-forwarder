<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

final class JsonConfigurationAddress
{
    /**
     * @param non-empty-string $name
     * @param non-empty-string $email
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
    ) {
    }
}
