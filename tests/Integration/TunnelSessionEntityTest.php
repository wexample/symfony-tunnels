<?php

namespace Wexample\SymfonyTunnels\Tests\Integration;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Enum\TunnelSessionStatus;
use Wexample\SymfonyTunnels\Repository\TunnelSessionRepository;
use Wexample\SymfonyTunnels\Repository\TunnelSessionVariableRepository;
use Wexample\SymfonyTunnels\Tests\Traits\DatabaseTestTrait;

class TunnelSessionEntityTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private const string TUNNEL_NAME = 'test-tunnel';

    private TunnelSessionRepository $sessionRepository;

    private TunnelSessionVariableRepository $variableRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->createDatabaseSchema();

        $this->sessionRepository = self::getContainer()->get(TunnelSessionRepository::class);
        $this->variableRepository = self::getContainer()->get(TunnelSessionVariableRepository::class);
    }

    public function testSessionIsCreatedOpenedWithARandomHash(): void
    {
        $session = $this->sessionRepository->saveNewTunnelSession(self::TUNNEL_NAME);

        $this->assertNotNull($session->getId());
        $this->assertSame(TunnelSessionStatus::OPENED, $session->getStatus());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $session->getHash());

        $other = $this->sessionRepository->saveNewTunnelSession(self::TUNNEL_NAME);
        $this->assertNotSame($session->getHash(), $other->getHash());
    }

    public function testResumeByHashChecksTunnelAndUser(): void
    {
        $session = $this->sessionRepository->saveNewTunnelSession(
            self::TUNNEL_NAME,
            userIdentifier: 'alice@example.com'
        );

        $this->assertSame(
            $session->getId(),
            $this->sessionRepository->findOneByHashForTunnel(
                $session->getHash(),
                self::TUNNEL_NAME,
                'alice@example.com'
            )?->getId()
        );

        $this->assertNull(
            $this->sessionRepository->findOneByHashForTunnel(
                $session->getHash(),
                'another-tunnel',
                'alice@example.com'
            )
        );

        $this->assertNull(
            $this->sessionRepository->findOneByHashForTunnel(
                $session->getHash(),
                self::TUNNEL_NAME,
                'bob@example.com'
            )
        );
    }

    public function testGlobalAndCursorVariablesAreSeparateScopes(): void
    {
        $session = $this->sessionRepository->saveNewTunnelSession(self::TUNNEL_NAME);

        $this->variableRepository->saveNewTunnelSessionVariable(
            $session,
            'amount',
            ['currency' => 'EUR', 'value' => 12]
        );
        $this->variableRepository->saveNewTunnelSessionVariable(
            $session,
            'amount',
            'cursor-scoped',
            'a1b2c3'
        );

        $global = $this->variableRepository->findOneByNameForCursor($session, 'amount');
        $this->assertSame(['currency' => 'EUR', 'value' => 12], $global?->getValue());
        $this->assertTrue($global->isGlobal());

        $scoped = $this->variableRepository->findOneByNameForCursor($session, 'amount', 'a1b2c3');
        $this->assertSame('cursor-scoped', $scoped?->getValue());
        $this->assertFalse($scoped->isGlobal());

        $this->assertNull(
            $this->variableRepository->findOneByNameForCursor($session, 'amount', 'unknown-cursor')
        );
    }

    public function testOnlyOpenedSessionsExpire(): void
    {
        $expirationDate = new DateTime('-1 day');

        $opened = $this->sessionRepository->saveNewTunnelSession(self::TUNNEL_NAME);
        $opened->setDateCreated(new DateTime('-2 days'));

        $completed = $this->sessionRepository->saveNewTunnelSession(self::TUNNEL_NAME);
        $completed->setDateCreated(new DateTime('-2 days'));
        $completed->setStatus(TunnelSessionStatus::COMPLETED);

        $recent = $this->sessionRepository->saveNewTunnelSession(self::TUNNEL_NAME);

        $this->sessionRepository->save($opened);
        $this->sessionRepository->save($completed);

        $this->assertTrue($opened->isExpired($expirationDate));
        $this->assertFalse($completed->isExpired($expirationDate));
        $this->assertFalse($recent->isExpired($expirationDate));

        $expired = $this->sessionRepository->findExpired($expirationDate);
        $this->assertSame(
            [$opened->getId()],
            array_map(static fn (TunnelSession $session): mixed => $session->getId(), $expired)
        );
    }
}
