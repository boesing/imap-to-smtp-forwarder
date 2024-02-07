<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration;

use Boesing\ImapToSmtpForwarder\AddressTransfer;

interface SmtpConfigurationInterface
{
    /**
     * @return non-empty-string
     */
    public function getIdentifier(): string;

    public function getSender(): AddressTransfer;

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
    public function getHostname(): string;

    /**
     * @return int<0,65535>
     */
    public function getPort(): int;
}
