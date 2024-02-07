<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

interface ImapConfigurationInterface
{
    /**
     * @return non-empty-string
     */
    public function getHostname(): string;

    /**
     * @return int<0,65535>
     */
    public function getPort(): int;

    /**
     * @return non-empty-string
     */
    public function getUsername(): string;

    /**
     * @return non-empty-string
     */
    public function getPassword(): string;

    /**
     * @return non-empty-string
     */
    public function getIdentifier(): string;
}
