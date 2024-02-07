<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use InvalidArgumentException;

interface ConfigurationFactoryInterface
{
    /**
     * @param non-empty-string $pathToConfigurationFile
     * @throws InvalidArgumentException In case configuration file is either missing or contains invalid configuration.
     */
    public function createFromConfigurationFile(string $pathToConfigurationFile): ConfigurationInterface;
}
