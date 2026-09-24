<?php

namespace Wexample\SymfonyTunnels\Exception;

use InvalidArgumentException;

/**
 * The values a tunnel was opened with do not match what it declared it needs.
 */
class TunnelInitVariableException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        string $tunnelName,
        string $variableName,
    ) {
        parent::__construct(
            $message . ' for variable "' . $variableName . '" in tunnel "' . $tunnelName . '".'
        );
    }
}
