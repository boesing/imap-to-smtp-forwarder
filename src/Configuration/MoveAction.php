<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Override;

final readonly class MoveAction implements MoveActionInterface
{
    /**
     * @param non-empty-string $inboxToMove
     */
    public function __construct(
        private string $inboxToMove,
        private bool $markAsRead,
    ) {
    }

    #[Override]
    public function getInboxToMove(): string
    {
        return $this->inboxToMove;
    }

    #[Override]
    public function markAsRead(): bool
    {
        return $this->markAsRead;
    }
}
