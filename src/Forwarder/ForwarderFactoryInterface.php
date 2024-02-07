<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Forwarder;

use Boesing\ImapToSmtpForwarder\Configuration\ForwardConfigurationInterface;

interface ForwarderFactoryInterface
{
    public function createForwarderFromConfiguration(ForwardConfigurationInterface $forwardConfiguration): ForwarderInterface;
}
