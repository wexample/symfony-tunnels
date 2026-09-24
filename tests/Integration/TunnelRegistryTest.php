<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Wexample\SymfonyTunnels\Service\TunnelRegistry;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;

class TunnelRegistryTest extends KernelTestCase
{
    public function testDeclaredTunnelsAreFoundByNameAndClass(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(TunnelRegistry::class);

        $this->assertInstanceOf(TestTunnelManagerService::class, $registry->getTunnel('test'));
        $this->assertSame(
            $registry->getTunnel('test'),
            $registry->getTunnel(TestTunnelManagerService::class)
        );
        $this->assertNull($registry->getTunnel('unknown'));
    }

    public function testTheInfoCommandPrintsTheMap(): void
    {
        $tester = new CommandTester(
            (new Application(self::bootKernel()))->find('tunnels:info')
        );

        $this->assertSame(0, $tester->execute(['name' => 'test']));

        $lines = explode(PHP_EOL, trim($tester->getDisplay()));

        $this->assertCount(10, $lines);
        // The entrypoint is on every one of the 21 paths.
        $this->assertSame(str_repeat('● ', 21) . 'step-one', $lines[0]);
        // Sibling branches read in the order they were declared.
        $this->assertStringEndsWith('step-two', $lines[1]);
        $this->assertStringEndsWith('step-two {"sit":"amet"}', $lines[3]);
        $this->assertStringEndsWith('step-four', $lines[8]);
        $this->assertStringEndsWith('step-five {"variant-id":1}', $lines[9]);
    }

    public function testTheInfoCommandListsKnownTunnelsOnAMiss(): void
    {
        $tester = new CommandTester(
            (new Application(self::bootKernel()))->find('tunnels:info')
        );

        $this->assertSame(1, $tester->execute(['name' => 'unknown']));
        $this->assertMatchesRegularExpression('/Known tunnels: .*\btest\b/', $tester->getDisplay());
    }
}
