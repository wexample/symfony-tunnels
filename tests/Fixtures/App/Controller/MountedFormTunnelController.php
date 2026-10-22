<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\FormTunnel\FormTunnelManagerService;

/**
 * The form tunnel a second time, mounted inside pages of the application: its
 * route takes the path and the name prefix of the class, and the steps follow
 * the page URL.
 */
#[Route(path: 'pages/form/', name: 'pages_form_')]
class MountedFormTunnelController extends AbstractTunnelController
{
    public static function getTunnelManagerClass(): string
    {
        return FormTunnelManagerService::class;
    }

    #[TunnelRoute(cursorPlaceholder: '{step}')]
    public function index(Request $request): Response
    {
        return $this->handleTunnelRequest($request);
    }
}
