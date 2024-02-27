<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

final class MoveAction implements MoveActionInterface
{
    /**
     * @param non-empty-string $inboxToMove
     */
    public function __construct(
        private readonly string $inboxToMove,
        private readonly bool $markAsRead,
    ) {
    }

    public function getInboxToMove(): string
    {
        return $this->inboxToMove;
    }

    public function markAsRead(): bool
    {
        return $this->markAsRead;
    }
}
