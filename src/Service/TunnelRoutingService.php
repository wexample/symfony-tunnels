<?php

namespace Wexample\SymfonyTunnels\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Class\TunnelCursor;

/**
 * The URL of a cursor: the tunnel route, the step in its path, and the options
 * in the query string when the cursor has some — which is what lets the next
 * request land on this exact variant of the step.
 */
class TunnelRoutingService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param class-string<AbstractTunnelManagerService>|AbstractTunnelManagerService $tunnel
     */
    public static function buildTunnelRouteName(
        string|AbstractTunnelManagerService $tunnel,
        string $name = TunnelRoute::NAME_INDEX,
    ): string {
        return 'tunnel_' . TextHelper::toSnake($tunnel::getName()) . '_' . $name;
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
            self::buildTunnelRouteName($cursor->manager, $routeName),
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
