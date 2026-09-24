<?php

namespace Wexample\SymfonyTunnels\Service;

use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Exception\TunnelCycleException;
use Wexample\SymfonyTunnels\Interface\TunnelVariableStorageInterface;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

/**
 * One tunnel: its tree of cursors, the session being walked, and what that
 * session holds.
 *
 * A manager carries the state of the flow in progress, which is why there is one
 * per tunnel rather than one for all of them, and why steps carry none.
 */
abstract class AbstractTunnelManagerService
{
    /**
     * @var array<string, TunnelCursor>
     */
    protected array $cursors = [];

    protected ?TunnelCursor $entrypointCursor = null;

    protected ?TunnelCursor $currentCursor = null;

    protected ?TunnelSession $session = null;

    public function __construct(
        protected readonly TunnelVariableStorageInterface $variableStorage,
    ) {
    }

    abstract public static function getName(): string;

    /**
     * The step the tunnel opens on.
     */
    abstract public function getEntrypointStep(): AbstractTunnelStep;

    /**
     * Build the whole tree, from the entrypoint down.
     */
    public function createEntrypoint(): TunnelCursor
    {
        $this->cursors = [];

        return $this->entrypointCursor = $this->getEntrypointStep()->createCursor($this);
    }

    public function getEntrypointCursor(): ?TunnelCursor
    {
        return $this->entrypointCursor;
    }

    /**
     * @throws TunnelCycleException when a step is reachable from itself
     */
    public function addCursor(TunnelCursor $cursor): void
    {
        $this->assertNoCycle($cursor);

        $this->cursors[$cursor->hash] = $cursor;
    }

    public function getCursor(string $hash): ?TunnelCursor
    {
        return $this->cursors[$hash] ?? null;
    }

    /**
     * @return array<string, TunnelCursor>
     */
    public function getCursors(): array
    {
        return $this->cursors;
    }

    public function getCurrentCursor(): ?TunnelCursor
    {
        return $this->currentCursor;
    }

    public function setCurrentCursor(?TunnelCursor $cursor): void
    {
        $this->currentCursor = $cursor;
    }

    public function selectNextCursor(TunnelCursor $cursor): ?TunnelCursor
    {
        return $cursor->step->selectNextCursor($cursor);
    }

    public function getSession(): ?TunnelSession
    {
        return $this->session;
    }

    public function setSession(?TunnelSession $session): void
    {
        $this->session = $session;
    }

    public function updateLastAccessedCursor(TunnelCursor $cursor): void
    {
        $this->requireSession()->setLastAccessedCursorHash($cursor->hash);
    }

    public function getVariableValue(
        string $name,
        ?TunnelCursor $cursor = null,
        mixed $default = null,
    ): mixed {
        return $this->variableStorage->getVariableValue(
            $this->requireSession(),
            $name,
            $cursor?->hash,
            $default
        );
    }

    public function setVariableValue(
        string $name,
        mixed $value,
        ?TunnelCursor $cursor = null,
        bool $initial = false,
    ): void {
        $this->variableStorage->setVariableValue(
            $this->requireSession(),
            $name,
            $value,
            $cursor?->hash,
            $initial
        );
    }

    public function removeVariable(
        string $name,
        ?TunnelCursor $cursor = null,
    ): bool {
        return $this->variableStorage->removeVariable(
            $this->requireSession(),
            $name,
            $cursor?->hash
        );
    }

    /**
     * Drop everything the cursor had stored, so walking it again starts fresh.
     */
    public function resetCursor(TunnelCursor $cursor): int
    {
        return $this->variableStorage->removeCursorVariables(
            $this->requireSession(),
            $cursor->hash
        );
    }

    protected function requireSession(): TunnelSession
    {
        if (!$this->session) {
            throw new \LogicException(
                'The tunnel ' . static::getName() . ' is used outside of any session.'
            );
        }

        return $this->session;
    }

    /**
     * @throws TunnelCycleException
     */
    private function assertNoCycle(TunnelCursor $cursor): void
    {
        $relativeHash = $cursor->buildRelativeHash();

        $cursor->forEachPreviousRecursive(
            static function (TunnelCursor $previous) use ($cursor, $relativeHash): void {
                if ($previous->buildRelativeHash() === $relativeHash) {
                    throw new TunnelCycleException($cursor);
                }
            }
        );
    }
}
