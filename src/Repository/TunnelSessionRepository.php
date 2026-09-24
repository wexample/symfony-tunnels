<?php

namespace Wexample\SymfonyTunnels\Repository;

use DateTimeInterface;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Entity\Traits\Manipulator\TunnelSessionEntityManipulatorTrait;
use Wexample\SymfonyTunnels\Enum\TunnelSessionStatus;

/**
 * @method TunnelSession|null find($id, $lockMode = null, $lockVersion = null)
 * @method TunnelSession|null findOneBy(array $criteria, array $orderBy = null)
 * @method TunnelSession      saveNewTunnelSession(string $tunnel, ?string $ipV4 = null, ?string $userIdentifier = null, ?string $browserData = null)
 * @method TunnelSession[]    findAll()
 * @method TunnelSession[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TunnelSessionRepository extends AbstractRepository
{
    use TunnelSessionEntityManipulatorTrait;

    public function createNewTunnelSession(
        string $tunnel,
        ?string $ipV4 = null,
        ?string $userIdentifier = null,
        ?string $browserData = null,
    ): TunnelSession {
        $session = new TunnelSession();

        $session
            ->setTunnel($tunnel)
            ->setIpV4($ipV4)
            ->setUserIdentifier($userIdentifier)
            ->setBrowserData($browserData)
            ->setDateCreatedNow();

        return $session;
    }

    /**
     * A resume hash alone is not enough to reopen a session: it must belong to
     * the tunnel being walked, and to the same visitor.
     */
    public function findOneByHashForTunnel(
        string $hash,
        string $tunnel,
        ?string $userIdentifier,
    ): ?TunnelSession {
        return $this->findOneBy([
            'hash' => $hash,
            'tunnel' => $tunnel,
            'userIdentifier' => $userIdentifier,
        ]);
    }

    /**
     * @return TunnelSession[]
     */
    public function findExpired(DateTimeInterface $expirationDate): array
    {
        return $this->createQueryBuilder('session')
            ->where('session.status = :status')
            ->andWhere('session.dateCreated < :expirationDate')
            ->setParameter('status', TunnelSessionStatus::OPENED)
            ->setParameter('expirationDate', $expirationDate)
            ->getQuery()
            ->getResult();
    }
}
