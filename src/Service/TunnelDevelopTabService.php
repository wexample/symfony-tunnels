<?php

namespace Wexample\SymfonyTunnels\Service;

use Wexample\SymfonyLoader\Interface\DevelopTabInterface;

/**
 * The tunnels tab of the loader's develop toolbar.
 */
class TunnelDevelopTabService implements DevelopTabInterface
{
    public function getId(): string
    {
        return 'tunnels';
    }

    public function getLabel(): string
    {
        return 'Tunnels';
    }

    public function getComponentPath(): string
    {
        return '@WexampleSymfonyTunnelsBundle/components/develop-tunnels';
    }
}
