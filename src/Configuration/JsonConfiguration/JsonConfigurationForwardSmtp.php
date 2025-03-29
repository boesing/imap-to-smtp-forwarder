<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

final readonly class JsonConfigurationForwardSmtp
{
    /**
     * @param non-empty-string $identifier
     * @param non-empty-string $sender
     */
    public function __construct(
        public string $identifier,
        public string $sender,
    ) {
    }
}
