<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder;

final class AddressTransfer
{
    /**
     * @param non-empty-string      $email
     * @param non-empty-string|null $name
     */
    public function __construct(
        public readonly string $email,
        public readonly string|null $name,
    ) {
    }
}
