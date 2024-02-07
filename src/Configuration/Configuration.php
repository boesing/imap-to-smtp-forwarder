<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

final class Configuration implements ConfigurationInterface
{
    /**
     * @param non-empty-list<ForwardConfigurationInterface> $forwards
     */
    public function __construct(
        private readonly array $forwards,
    ) {
    }

    public function getForwardConfiguration(): iterable
    {
        return $this->forwards;
    }
}
