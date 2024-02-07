<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

final class JsonConfiguration
{
    /**
     * @param non-empty-array<non-empty-string, JsonConfigurationAddress> $addresses
     * @param non-empty-array<non-empty-string,non-empty-list<string>>    $templates
     * @param non-empty-array<non-empty-string,JsonConfigurationImap>     $imap
     * @param non-empty-array<non-empty-string,JsonConfigurationSmtp>     $smtp
     * @param non-empty-list<JsonConfigurationForward>                    $forwards
     */
    public function __construct(
        public readonly array $addresses,
        public readonly array $templates,
        public readonly array $imap,
        public readonly array $smtp,
        public readonly array $forwards,
    ) {
    }
}
