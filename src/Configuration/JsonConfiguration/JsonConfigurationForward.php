<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

use InvalidArgumentException;

final readonly class JsonConfigurationForward
{
    /**
     * @param non-empty-string                 $imap
     * @param non-empty-list<non-empty-string> $recipients
     * @param non-empty-string                 $template
     * @param non-empty-string                 $inbox
     * @param non-empty-string|null            $inboxToMove
     */
    public function __construct(
        public string $imap,
        public JsonConfigurationForwardSmtp $smtp,
        public array $recipients,
        public string $template,
        public JsonConfigurationForwardActionEnum $action,
        public string $inbox = 'INBOX',
        public string|null $inboxToMove = null,
        public bool $markAsRead = true,
    ) {
        if ($this->action === JsonConfigurationForwardActionEnum::MOVE && $this->inboxToMove === null) {
            throw new InvalidArgumentException('`move` action needs `inboxToMove` configuration.');
        }
    }
}
