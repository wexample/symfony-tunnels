<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\FormTunnel\FormTunnelManagerService;

class FormTunnelController extends AbstractTunnelController
{
    public static function getTunnelManagerClass(): string
    {
        return FormTunnelManagerService::class;
    }

    #[TunnelRoute]
    public function index(Request $request): Response
    {
        return $this->handleTunnelRequest($request);
    }
}
