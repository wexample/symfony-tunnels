<?php

namespace Wexample\SymfonyTunnels\Controller;

use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;

/**
 * The HTTP side of one tunnel. Its actions carry #[TunnelRoute], and the tunnel
 * they serve is named here, once, rather than repeated in every route.
 */
abstract class AbstractTunnelController extends AbstractPagesController
{
    public const string TAG_CONTROLLER = 'wexample.symfony_tunnels.controller';

    /**
     * @return class-string<AbstractTunnelManagerService>
     */
    abstract public static function getTunnelManagerClass(): string;
}
