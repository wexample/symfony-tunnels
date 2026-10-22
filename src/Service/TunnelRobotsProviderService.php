<?php

namespace Wexample\SymfonyTunnels\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Wexample\SymfonySeo\Class\RobotsRule;
use Wexample\SymfonySeo\Interface\RobotsProviderInterface;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Helper\TunnelRouteHelper;
use Wexample\SymfonyTunnels\Routing\TunnelRouteLoader;

/**
 * Keeps crawlers out of the tunnels: their urls only mean something within a
 * session, and every visit opens one in the database. Each tunnel route is
 * named by the fixed start of its path, wherever it is mounted.
 */
class TunnelRobotsProviderService implements RobotsProviderInterface
{
    /**
     * @param array<class-string<AbstractTunnelController>> $controllerClasses
     */
    public function __construct(
        #[Autowire(param: TunnelRouteLoader::PARAMETER_CONTROLLERS)]
        private readonly array $controllerClasses,
    ) {
    }

    public function getRobotsRules(): array
    {
        return [
            new RobotsRule(
                disallow: TunnelRouteHelper::buildPathPrefixes($this->controllerClasses),
            ),
        ];
    }
}
