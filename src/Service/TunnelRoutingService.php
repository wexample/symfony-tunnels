<?php

namespace Wexample\SymfonyTunnels\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Helper\TunnelRouteHelper;
use Wexample\SymfonyTunnels\Routing\TunnelRouteLoader;

/**
 * The URL of a cursor: the tunnel route, the step in its path, and the options
 * in the query string when the cursor has some — which is what lets the next
 * request land on this exact variant of the step.
 */
class TunnelRoutingService
{
    /**
     * @param array<class-string<AbstractTunnelController>> $controllerClasses
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        #[Autowire(param: TunnelRouteLoader::PARAMETER_CONTROLLERS)]
        private readonly array $controllerClasses,
    ) {
    }

    /**
     * The route of a tunnel. Mounted by several controllers, the tunnel stays
     * on the one the request is walking it through.
     *
     * @param class-string<AbstractTunnelManagerService>|AbstractTunnelManagerService $tunnel
     */
    public function buildTunnelRouteName(
        string|AbstractTunnelManagerService $tunnel,
        string $name = TunnelRoute::NAME_INDEX,
    ): string {
        $tunnelName = $tunnel::getName();
        $mounts = array_values(array_filter(
            $this->controllerClasses,
            static fn (string $controllerClass): bool => $controllerClass::getTunnelManagerClass()::getName() === $tunnelName
        ));

        $current = explode('::', (string) $this->requestStack->getCurrentRequest()?->attributes->get('_controller'))[0];
        $controllerClass = in_array($current, $mounts, true) ? $current : ($mounts[0] ?? null);

        if (! $controllerClass) {
            throw new \LogicException(sprintf('No controller mounts the tunnel "%s".', $tunnelName));
        }

        return TunnelRouteHelper::buildRouteName($controllerClass, $name);
    }

    public function buildCursorUrl(
        TunnelCursor $cursor,
        array $params = [],
        string $routeName = TunnelRoute::NAME_INDEX,
        int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH,
    ): string {
        if ($cursor->options) {
            $params += [TunnelCursor::QUERY_STRING_CURSOR_OPTIONS => $cursor->options];
        }

        return $this->urlGenerator->generate(
            $this->buildTunnelRouteName($cursor->manager, $routeName),
            $this->buildCursorRouteParams($cursor, $params),
            $referenceType
        );
    }

    public function buildCursorRouteParams(
        TunnelCursor $cursor,
        array $extraParams = [],
    ): array {
        return $extraParams
            + $cursor->manager->buildRouteParams($cursor)
            + $cursor->step->buildRouteParams($cursor);
    }
}
