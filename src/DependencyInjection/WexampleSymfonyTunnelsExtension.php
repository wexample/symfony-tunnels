<?php

namespace Wexample\SymfonyTunnels\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\TunnelRegistry;

class WexampleSymfonyTunnelsExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $this->loadConfig(
            __DIR__,
            $container
        );

        // Tunnels live in the applications, outside of this bundle's own
        // services: the tag has to follow the class wherever it is declared.
        $container
            ->registerForAutoconfiguration(AbstractTunnelManagerService::class)
            ->addTag(TunnelRegistry::TAG_TUNNEL);
    }
}
