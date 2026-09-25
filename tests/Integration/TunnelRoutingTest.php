<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Wexample\SymfonyTunnels\Service\TunnelRoutingService;
use Wexample\SymfonyTunnels\Tests\Fixtures\App\Controller\MountedFormTunnelController;
use Wexample\SymfonyTunnels\Tests\Fixtures\App\Controller\TestTunnelController;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThree;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;

class TunnelRoutingTest extends KernelTestCase
{
    public function testTheControllerAttributeBecomesARouteNamedAfterTheTunnel(): void
    {
        self::bootKernel();
        $route = self::getContainer()->get(RouterInterface::class)
            ->getRouteCollection()
            ->get('tunnel_test_index');

        $this->assertNotNull($route);
        $this->assertSame('/tunnel/test-tunnel/with/prefix/{step}', $route->getPath());
        $this->assertNull($route->getDefault('step'));
        $this->assertSame(TestTunnelController::class . '::index', $route->getDefault('_controller'));
    }

    public function testACursorUrlCarriesItsStepAndItsOptions(): void
    {
        self::bootKernel();
        $routing = self::getContainer()->get(TunnelRoutingService::class);
        $entrypoint = self::getContainer()->get(TestTunnelManagerService::class)->createEntrypoint();

        $this->assertSame(
            '/tunnel/test-tunnel/with/prefix/step-one',
            $routing->buildCursorUrl($entrypoint)
        );

        $stepThree = $entrypoint->findFirstNext()->findFirstNextByStep(StepThree::class);
        $this->assertSame(
            '/tunnel/test-tunnel/with/prefix/step-three',
            $routing->buildCursorUrl($stepThree)
        );

        $sitAmet = $entrypoint->findFirstNextByOptions(['sit' => 'amet']);
        $this->assertSame(
            '/tunnel/test-tunnel/with/prefix/step-two?cursor-options%5Bsit%5D=amet',
            $routing->buildCursorUrl($sitAmet)
        );
    }

    public function testAControllerWithAClassRouteMountsTheTunnelInsideItsPages(): void
    {
        self::bootKernel();
        $route = self::getContainer()->get(RouterInterface::class)
            ->getRouteCollection()
            ->get('pages_form_index');

        $this->assertNotNull($route);
        $this->assertSame('/pages/form/{step}', $route->getPath());
        $this->assertSame(MountedFormTunnelController::class . '::index', $route->getDefault('_controller'));
    }
}
