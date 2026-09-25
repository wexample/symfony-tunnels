<?php

namespace Wexample\SymfonyTunnels\Routing;

use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Wexample\SymfonyHelpers\Routing\AbstractRouteLoader;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Helper\TunnelRouteHelper;

/**
 * Turns every #[TunnelRoute] of every tunnel controller into a route, without
 * the application listing its controllers anywhere. Where each route lives and
 * how it is named is TunnelRouteHelper's call.
 */
class TunnelRouteLoader extends AbstractRouteLoader
{
    public const string PARAMETER_CONTROLLERS = 'wexample_symfony_tunnels.controllers';

    /**
     * @param array<class-string<AbstractTunnelController>> $controllerClasses
     */
    public function __construct(
        private readonly array $controllerClasses,
        ContainerInterface $container,
        ?string $env = null,
    ) {
        parent::__construct($container, $env);
    }

    protected function getName(): string
    {
        return 'tunnel_routes';
    }

    protected function loadOnce(
        $resource,
        ?string $type = null
    ): RouteCollection {
        $routes = new RouteCollection();

        foreach ($this->controllerClasses as $controllerClass) {
            foreach ((new ReflectionClass($controllerClass))->getMethods() as $method) {
                foreach ($method->getAttributes(TunnelRoute::class) as $attribute) {
                    /** @var TunnelRoute $tunnelRoute */
                    $tunnelRoute = $attribute->newInstance();

                    $routes->add(
                        TunnelRouteHelper::buildRouteName($controllerClass, $tunnelRoute->name),
                        new Route(
                            TunnelRouteHelper::buildPath($controllerClass, $tunnelRoute),
                            ['_controller' => $controllerClass . '::' . $method->getName()]
                        )
                    );
                }
            }
        }

        return $routes;
    }
}
