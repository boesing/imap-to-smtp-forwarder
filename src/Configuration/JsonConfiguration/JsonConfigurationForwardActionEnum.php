<?php

declare(strict_types=1);

namespace Boesing\ImapToSmtpForwarder\Configuration\JsonConfiguration;

enum JsonConfigurationForwardActionEnum: string
{
    case DELETE = 'delete';
    case MOVE   = 'move';
}
