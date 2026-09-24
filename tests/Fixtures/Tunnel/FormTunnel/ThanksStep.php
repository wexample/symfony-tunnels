<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\FormTunnel;

use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

class ThanksStep extends AbstractTunnelStep
{
    public static function getName(): string
    {
        return 'thanks';
    }
}
