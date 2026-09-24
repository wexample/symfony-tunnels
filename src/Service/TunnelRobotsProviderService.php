<?php

namespace Wexample\SymfonyTunnels\Service;

use Wexample\SymfonySeo\Class\RobotsRule;
use Wexample\SymfonySeo\Interface\RobotsProviderInterface;
use Wexample\SymfonyTunnels\Routing\TunnelRouteLoader;

/**
 * Keeps crawlers out of the tunnels: their urls only mean something within a
 * session, and every visit opens one in the database.
 */
class TunnelRobotsProviderService implements RobotsProviderInterface
{
    public function getRobotsRules(): array
    {
        return [
            new RobotsRule(
                disallow: ['/' . TunnelRouteLoader::PATH_PREFIX . '/'],
            ),
        ];
    }
}
