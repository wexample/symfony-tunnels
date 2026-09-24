<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Wexample\SymfonyTunnels\Repository\TunnelSessionRepository;
use Wexample\SymfonyTunnels\Service\TunnelRegistry;
use Wexample\SymfonyTunnels\Service\TunnelSessionService;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\TestTunnelManagerService;
use Wexample\SymfonyTunnels\Tests\Traits\DatabaseTestTrait;

class TunnelRegistryTest extends KernelTestCase
{
    use DatabaseTestTrait;

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

    public function testTheInfoCommandShowsWhatASessionHolds(): void
    {
        $kernel = self::bootKernel();
        $this->createDatabaseSchema();

        $tunnel = self::getContainer()->get(TestTunnelManagerService::class);
        $session = self::getContainer()->get(TunnelSessionService::class)->findOrCreateSession('test');
        $tunnel->setSession($session);
        $entrypoint = $tunnel->createEntrypoint();
        $entrypoint->step->initAsCurrentStep($entrypoint);
        $tunnel->setVariableValue('where', 'everywhere');

        $tester = new CommandTester((new Application($kernel))->find('tunnels:info'));

        $this->assertSame(0, $tester->execute(['name' => 'test', '--session' => $session->getHash()]));

        $display = $tester->getDisplay();
        $this->assertStringContainsString('status opened', $display);
        $this->assertMatchesRegularExpression('/step-one\s*\|\s*tunnel-step-complete\s*\|\s*true/', $display);
        $this->assertMatchesRegularExpression('/global\s*\|\s*where\s*\|\s*"everywhere"/', $display);

        $this->assertSame(1, $tester->execute(['name' => 'test', '--session' => 'unknown']));
    }

    public function testThePurgeCommandDropsTheExpiredSessions(): void
    {
        $kernel = self::bootKernel();
        $this->createDatabaseSchema();

        $sessionService = self::getContainer()->get(TunnelSessionService::class);
        $repository = self::getContainer()->get(TunnelSessionRepository::class);

        $expired = $sessionService->findOrCreateSession('test');
        $expired->setDateExpiration(new DateTime('-1 minute'));
        $repository->save($expired);
        $expiredId = $expired->getId();

        $kept = $sessionService->findOrCreateSession('test');

        $tester = new CommandTester((new Application($kernel))->find('tunnels:purge'));

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('1 expired session(s) dropped.', $tester->getDisplay());
        $this->assertNull($repository->find($expiredId));
        $this->assertNotNull($repository->find($kept->getId()));
    }

    public function testThePurgeIsScheduled(): void
    {
        $kernel = self::bootKernel();

        $tester = new CommandTester((new Application($kernel))->find('debug:scheduler'));
        $tester->execute([]);

        $this->assertStringContainsString(TunnelRegistry::PURGE_FREQUENCY, $tester->getDisplay());
        $this->assertStringContainsString('purgeExpiredSessions', $tester->getDisplay());
    }
}
