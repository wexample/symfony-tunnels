<?php

namespace Wexample\SymfonyTunnels\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Routing\TunnelRouteLoader;

/**
 * Hands the route loader the tunnel controller classes, so that loading routes
 * reads attributes rather than builds controllers and everything they depend on.
 */
class TunnelControllersCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $classes = [];

        foreach (array_keys($container->findTaggedServiceIds(AbstractTunnelController::TAG_CONTROLLER)) as $id) {
            $classes[] = $container->getDefinition($id)->getClass() ?? $id;
        }

        $container->setParameter(TunnelRouteLoader::PARAMETER_CONTROLLERS, $classes);
    }
}
