<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyTunnels\Service\TunnelSessionService;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepThree;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;
use Wexample\SymfonyTunnels\Tests\Traits\DatabaseTestTrait;
use Wexample\SymfonyTunnels\Twig\TunnelExtension;

class TunnelExtensionTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private TestTunnelManagerService $tunnel;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->createDatabaseSchema();

        $this->tunnel = self::getContainer()->get(TestTunnelManagerService::class);
        $this->tunnel->setSession(
            self::getContainer()->get(TunnelSessionService::class)->findOrCreateSession('test')
        );
    }

    public function testWhereBranchesPartTheStepperShowsAnUnknownGap(): void
    {
        $entrypoint = $this->tunnel->createEntrypoint();

        $stepper = self::getContainer()->get(TunnelExtension::class)->tunnelStepper($entrypoint);

        $this->assertSame(0, $stepper['current']);
        $this->assertSame('Step one', $stepper['steps'][0]['label']);
        $this->assertSame('Step two', $stepper['steps'][1]['label']);
        $this->assertSame(['unknown' => true], $stepper['steps'][2]);
        $this->assertCount(3, $stepper['steps']);
    }

    public function testOnABranchEveryStepIsKnownAndOnlyTheReachableOnesAreLinked(): void
    {
        $stepThree = $this->tunnel->createEntrypoint()->findFirstNext()->findFirstNextByStep(StepThree::class);

        $stepper = self::getContainer()->get(TunnelExtension::class)->tunnelStepper($stepThree);

        $this->assertSame(2, $stepper['current']);
        $this->assertSame(
            ['Step one', 'Step two', 'Step three', 'Step four'],
            array_column($stepper['steps'], 'label')
        );
        $this->assertSame('/tunnel/test-tunnel/with/prefix/step-one', $stepper['steps'][0]['href']);
        $this->assertNull($stepper['steps'][3]['href']);
    }
}
