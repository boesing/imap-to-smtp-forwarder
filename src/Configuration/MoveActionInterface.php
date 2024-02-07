<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

interface MoveActionInterface
{
    /**
     * @return non-empty-string
     */
    public function getInboxToMove(): string;
}
