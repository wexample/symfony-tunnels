<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test;

use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

abstract class AbstractTestStep extends AbstractTunnelStep
{
    public static function getName(): string
    {
        return static::STEP_NAME;
    }
}
