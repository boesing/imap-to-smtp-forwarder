<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Imap;

use Symfony\Component\Console\Output\OutputInterface;

interface ImapInterface
{
    /**
     * @param non-empty-string $inbox
     * @return iterable<MessageInterface>
     */
    public function fetch(string $inbox, OutputInterface $output): iterable;

    public function delete(MessageInterface $messageToForward): void;

    public function move(MessageInterface $messageToForward, string $inboxToMove, bool $markAsRead): void;
}
