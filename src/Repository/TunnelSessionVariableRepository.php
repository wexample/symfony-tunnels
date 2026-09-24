<?php

namespace Wexample\SymfonyTunnels\Repository;

use Wexample\SymfonyHelpers\Repository\AbstractRepository;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Entity\TunnelSessionVariable;
use Wexample\SymfonyTunnels\Entity\Traits\Manipulator\TunnelSessionVariableEntityManipulatorTrait;

/**
 * @method TunnelSessionVariable|null find($id, $lockMode = null, $lockVersion = null)
 * @method TunnelSessionVariable|null findOneBy(array $criteria, array $orderBy = null)
 * @method TunnelSessionVariable      saveNewTunnelSessionVariable(TunnelSession $tunnelSession, string $name, mixed $value, ?string $cursorHash = null, bool $initial = false)
 * @method TunnelSessionVariable[]    findAll()
 * @method TunnelSessionVariable[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TunnelSessionVariableRepository extends AbstractRepository
{
    use TunnelSessionVariableEntityManipulatorTrait;

    public function createNewTunnelSessionVariable(
        TunnelSession $tunnelSession,
        string $name,
        mixed $value,
        ?string $cursorHash = null,
        bool $initial = false,
    ): TunnelSessionVariable {
        $variable = new TunnelSessionVariable();

        $variable
            ->setName($name)
            ->setValue($value)
            ->setCursorHash($cursorHash)
            ->setInitial($initial)
            ->setDateCreatedNow();

        $variable->setTunnelSession($tunnelSession);
        $tunnelSession->addTunnelSessionVariable($variable);

        return $variable;
    }

    /**
     * A null cursor hash means the variable global to the session, which is a
     * scope of its own: it never matches a variable of the same name stored
     * under a cursor.
     */
    public function findOneByNameForCursor(
        TunnelSession $tunnelSession,
        string $name,
        ?string $cursorHash = null,
    ): ?TunnelSessionVariable {
        return $this->findOneBy([
            'tunnelSession' => $tunnelSession,
            'name' => $name,
            'cursorHash' => $cursorHash,
        ]);
    }

    /**
     * @return TunnelSessionVariable[]
     */
    public function findByCursorHash(
        TunnelSession $tunnelSession,
        string $cursorHash,
    ): array {
        return $this->findBy([
            'tunnelSession' => $tunnelSession,
            'cursorHash' => $cursorHash,
        ]);
    }
}
