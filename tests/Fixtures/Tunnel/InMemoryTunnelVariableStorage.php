<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel;

use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Interface\TunnelVariableStorageInterface;

/**
 * The engine's variable storage without a database, so that tree building,
 * completion strategies and branch resets can be tested on their own.
 */
class InMemoryTunnelVariableStorage implements TunnelVariableStorageInterface
{
    /**
     * Values keyed by session, then by cursor hash, then by name.
     *
     * @var array<int, array<string, array<string, mixed>>>
     */
    private array $values = [];

    public function getVariableValue(
        TunnelSession $session,
        string $name,
        ?string $cursorHash = null,
        mixed $default = null,
    ): mixed {
        return $this->values[spl_object_id($session)][$this->scope($cursorHash)][$name] ?? $default;
    }

    public function setVariableValue(
        TunnelSession $session,
        string $name,
        mixed $value,
        ?string $cursorHash = null,
        bool $initial = false,
    ): void {
        $this->values[spl_object_id($session)][$this->scope($cursorHash)][$name] = $value;
    }

    public function removeVariable(
        TunnelSession $session,
        string $name,
        ?string $cursorHash = null,
    ): bool {
        $sessionId = spl_object_id($session);
        $scope = $this->scope($cursorHash);

        if (!isset($this->values[$sessionId][$scope][$name])) {
            return false;
        }

        unset($this->values[$sessionId][$scope][$name]);

        return true;
    }

    public function removeCursorVariables(
        TunnelSession $session,
        string $cursorHash,
    ): int {
        $sessionId = spl_object_id($session);
        $count = count($this->values[$sessionId][$cursorHash] ?? []);

        unset($this->values[$sessionId][$cursorHash]);

        return $count;
    }

    private function scope(?string $cursorHash): string
    {
        return $cursorHash ?? '';
    }
}
