<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

final readonly class JsonConfiguration
{
    /**
     * @param non-empty-array<non-empty-string, JsonConfigurationAddress> $addresses
     * @param non-empty-array<non-empty-string,non-empty-list<string>>    $templates
     * @param non-empty-array<non-empty-string,JsonConfigurationImap>     $imap
     * @param non-empty-array<non-empty-string,JsonConfigurationSmtp>     $smtp
     * @param non-empty-list<JsonConfigurationForward>                    $forwards
     * @param positive-int                                                $loopDelay
     */
    public function __construct(
        public array $addresses,
        public array $templates,
        public array $imap,
        public array $smtp,
        public array $forwards,
        public int $loopDelay = 5,
    ) {
    }
}
