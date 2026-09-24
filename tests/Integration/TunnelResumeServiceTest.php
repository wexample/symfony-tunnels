<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Enum\TunnelSessionStatus;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\TunnelResumeService;
use Wexample\SymfonyTunnels\Service\TunnelSessionService;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\FormTunnel\FormTunnelManagerService;
use Wexample\SymfonyTunnels\Tests\Traits\DatabaseTestTrait;

class TunnelResumeServiceTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private const string VARIABLE_NAME = 'payment-id';

    public function testAPendingSessionIsResumedOnItsCursorAndCompleted(): void
    {
        self::bootKernel();
        $this->createDatabaseSchema();

        $sessionService = self::getContainer()->get(TunnelSessionService::class);
        $tunnel = self::getContainer()->get(FormTunnelManagerService::class);

        // A visitor reaches the step, which then waits for an outside event.
        $session = $sessionService->findOrCreateSession('form');
        $tunnel->setSession($session);
        $waiting = $tunnel->createEntrypoint();
        $waiting->setVariableValue(self::VARIABLE_NAME, 'pay_1');
        $tunnel->setSessionStatus(TunnelSessionStatus::PENDING_ASYNC_ACTION);

        // Another session waits on another event, a third holds the same value
        // but is not waiting anymore.
        $other = $sessionService->findOrCreateSession('form');
        $tunnel->setSession($other);
        $tunnel->createEntrypoint()->setVariableValue(self::VARIABLE_NAME, 'pay_2');
        $tunnel->setSessionStatus(TunnelSessionStatus::PENDING_ASYNC_ACTION);

        $done = $sessionService->findOrCreateSession('form');
        $tunnel->setSession($done);
        $tunnel->createEntrypoint()->setVariableValue(self::VARIABLE_NAME, 'pay_1');

        $received = [];
        $count = self::getContainer()->get(TunnelResumeService::class)->resumeByVariable(
            self::VARIABLE_NAME,
            'pay_1',
            static function (?TunnelCursor $cursor, AbstractTunnelManagerService $manager) use (&$received): void {
                $received[] = $cursor;
                $cursor->setComplete();
                $manager->setSessionComplete();
            }
        );

        $this->assertSame(1, $count);
        $this->assertSame($waiting->hash, $received[0]->hash);
        $this->assertSame(TunnelSessionStatus::COMPLETED, $session->getStatus());
        $this->assertSame(TunnelSessionStatus::PENDING_ASYNC_ACTION, $other->getStatus());
        $this->assertSame(TunnelSessionStatus::OPENED, $done->getStatus());
    }
}
