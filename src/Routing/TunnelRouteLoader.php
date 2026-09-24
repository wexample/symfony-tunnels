<?php

namespace Wexample\SymfonyTunnels\Routing;

use ReflectionClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyHelpers\Routing\AbstractRouteLoader;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Service\TunnelRoutingService;

/**
 * Turns every #[TunnelRoute] of every tunnel controller into a route, without
 * the application listing its controllers anywhere.
 */
class TunnelRouteLoader extends AbstractRouteLoader
{
    public const string PARAMETER_CONTROLLERS = 'wexample_symfony_tunnels.controllers';

    public const string PATH_PREFIX = 'tunnel';

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
            $controllerPathPart = TextHelper::toKebab(
                TextHelper::removeSuffix(ClassHelper::getShortName($controllerClass), 'Controller')
            );

            foreach ((new ReflectionClass($controllerClass))->getMethods() as $method) {
                foreach ($method->getAttributes(TunnelRoute::class) as $attribute) {
                    /** @var TunnelRoute $tunnelRoute */
                    $tunnelRoute = $attribute->newInstance();

                    $routes->add(
                        TunnelRoutingService::buildTunnelRouteName(
                            $controllerClass::getTunnelManagerClass(),
                            $tunnelRoute->name
                        ),
                        new Route(
                            $this->buildPath($controllerPathPart, $tunnelRoute),
                            ['_controller' => $controllerClass . '::' . $method->getName()]
                        )
                    );
                }
            }
        }

        return $routes;
    }

    private function buildPath(
        string $controllerPathPart,
        TunnelRoute $tunnelRoute,
    ): string {
        $parts = [
            self::PATH_PREFIX,
            $controllerPathPart,
            $tunnelRoute->name === TunnelRoute::NAME_INDEX ? null : $tunnelRoute->name,
            $tunnelRoute->pathPrefix,
            $tunnelRoute->cursorPlaceholder,
            $tunnelRoute->pathSuffix,
        ];

        return '/' . implode('/', array_filter($parts));
    }
}
