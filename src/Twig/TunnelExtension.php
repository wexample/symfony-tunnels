<?php

namespace Wexample\SymfonyTunnels\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Service\TunnelRoutingService;

class TunnelExtension extends AbstractExtension
{
    public function __construct(
        private readonly TunnelRoutingService $tunnelRoutingService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tunnel_cursor_url', $this->tunnelCursorUrl(...)),
        ];
    }

    public function tunnelCursorUrl(
        TunnelCursor $cursor,
        array $params = [],
    ): string {
        return $this->tunnelRoutingService->buildCursorUrl($cursor, $params);
    }
}
