<?php

namespace Wexample\SymfonyTunnels\Interface;

use Wexample\SymfonyTunnels\Entity\TunnelSession;

/**
 * Where a tunnel keeps what the visitor produced while walking it.
 *
 * A null cursor hash addresses the scope global to the session, which never
 * overlaps a variable of the same name stored under a cursor.
 */
interface TunnelVariableStorageInterface
{
    public function getVariableValue(
        TunnelSession $session,
        string $name,
        ?string $cursorHash = null,
        mixed $default = null,
    ): mixed;

    public function setVariableValue(
        TunnelSession $session,
        string $name,
        mixed $value,
        ?string $cursorHash = null,
        bool $initial = false,
    ): void;

    public function removeVariable(
        TunnelSession $session,
        string $name,
        ?string $cursorHash = null,
    ): bool;

    /**
     * @return int the number of variables dropped
     */
    public function removeCursorVariables(
        TunnelSession $session,
        string $cursorHash,
    ): int;
}
