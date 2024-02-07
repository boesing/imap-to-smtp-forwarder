<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Forwarder;

use Symfony\Component\Console\Output\OutputInterface;

interface ForwarderInterface
{
    public function forward(OutputInterface $output): void;
}
