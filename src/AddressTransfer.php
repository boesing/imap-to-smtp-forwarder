<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder;

final readonly class AddressTransfer
{
    /**
     * @param non-empty-string      $email
     * @param non-empty-string|null $name
     */
    public function __construct(
        public string $email,
        public string|null $name,
    ) {
    }
}
