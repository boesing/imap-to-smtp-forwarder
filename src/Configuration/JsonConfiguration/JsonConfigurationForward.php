<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

use InvalidArgumentException;

final class JsonConfigurationForward
{
    /**
     * @param non-empty-string                 $imap
     * @param non-empty-list<non-empty-string> $recipients
     * @param non-empty-string                 $template
     * @param non-empty-string                 $inbox
     * @param non-empty-string|null            $inboxToMove
     */
    public function __construct(
        public readonly string $imap,
        public readonly JsonConfigurationForwardSmtp $smtp,
        public readonly array $recipients,
        public readonly string $template,
        public readonly JsonConfigurationForwardActionEnum $action,
        public readonly string $inbox = 'INBOX',
        public readonly string|null $inboxToMove = null,
    ) {
        if ($this->action === JsonConfigurationForwardActionEnum::MOVE && $this->inboxToMove === null) {
            throw new InvalidArgumentException('`move` action needs `inboxToMove` configuration.');
        }
    }
}
