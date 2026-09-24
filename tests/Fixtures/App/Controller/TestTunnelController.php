<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;

class TestTunnelController extends AbstractTunnelController
{
    public static function getTunnelManagerClass(): string
    {
        return TestTunnelManagerService::class;
    }

    #[TunnelRoute(pathPrefix: 'with/prefix')]
    public function index(): Response
    {
        return new Response();
    }
}
