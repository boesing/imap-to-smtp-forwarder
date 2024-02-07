<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

interface ConfigurationInterface
{
    /**
     * @return iterable<ForwardConfigurationInterface>
     */
    public function getForwardConfiguration(): iterable;

    /**
     * @return positive-int
     */
    public function getLoopDelay(): int;
}
