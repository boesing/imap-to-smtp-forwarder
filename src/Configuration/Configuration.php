<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

final class Configuration implements ConfigurationInterface
{
    /**
     * @param non-empty-list<ForwardConfigurationInterface> $forwards
     * @param positive-int                                  $loopDelay
     */
    public function __construct(
        private readonly array $forwards,
        private readonly int $loopDelay,
    ) {
    }

    public function getForwardConfiguration(): iterable
    {
        return $this->forwards;
    }

    public function getLoopDelay(): int
    {
        return $this->loopDelay;
    }
}
