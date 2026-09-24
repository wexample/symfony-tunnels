<?php

namespace Wexample\SymfonyTunnels\Service;

use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Uid\Uuid;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Enum\TunnelSessionStatus;
use Wexample\SymfonyTunnels\Exception\TunnelCycleException;
use Wexample\SymfonyTunnels\Exception\TunnelInitVariableException;
use Wexample\SymfonyTunnels\Interface\TunnelSessionStorageInterface;
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

    /**
     * @var array<string, AbstractTunnelStep>
     */
    protected array $steps = [];

    /**
     * The values the tunnel was opened with, in the form they are stored.
     *
     * @var array<string, mixed>
     */
    protected array $initVariables = [];

    public function __construct(
        protected readonly TunnelSessionStorageInterface $sessionStorage,
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
        $this->steps = [];

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
        $this->steps[$cursor->step::getName()] = $cursor->step;
    }

    /**
     * @return array<string, AbstractTunnelStep> one entry per step, however many
     *                                           cursors it holds
     */
    public function getSteps(): array
    {
        return $this->steps;
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

        if ($session) {
            $this->saveInitVariables();
        }
    }

    public function updateLastAccessedCursor(TunnelCursor $cursor): void
    {
        $session = $this->requireSession();
        $session->setLastAccessedCursorHash($cursor->hash);

        $this->sessionStorage->saveSession($session);
    }

    public function setSessionStatus(TunnelSessionStatus $status): void
    {
        $session = $this->requireSession();
        $session->setStatus($status);

        $this->sessionStorage->saveSession($session);
    }

    public function setSessionComplete(): void
    {
        $this->setSessionStatus(TunnelSessionStatus::COMPLETED);
    }

    public function getVariableValue(
        string $name,
        ?TunnelCursor $cursor = null,
        mixed $default = null,
    ): mixed {
        return $this->sessionStorage->getVariableValue(
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
        $this->sessionStorage->setVariableValue(
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
        return $this->sessionStorage->removeVariable(
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
        return $this->sessionStorage->removeCursorVariables(
            $this->requireSession(),
            $cursor->hash
        );
    }

    /**
     * What the caller has to hand the tunnel when opening it, each entry giving
     * a `type`, and optionally `required`, `default` and an `autoInit` callable
     * turning a stored value back into what the tunnel expects.
     */
    public function getInitVariablesConfig(): array
    {
        return [];
    }

    /**
     * @throws TunnelInitVariableException when a value is missing or of the wrong type
     */
    public function setInitialVariables(array $variables): void
    {
        $config = $this->getInitVariablesConfig();

        foreach ($variables as $name => $value) {
            if (!isset($config[$name])) {
                throw new TunnelInitVariableException(
                    'Unexpected value, expected one of: ' . implode(', ', array_keys($config)),
                    static::getName(),
                    $name
                );
            }
        }

        foreach ($config as $name => $variableConfig) {
            if (!array_key_exists($name, $variables)) {
                if (array_key_exists('default', $variableConfig)) {
                    $variables[$name] = $variableConfig['default'];
                } elseif ($variableConfig['required'] ?? false) {
                    throw new TunnelInitVariableException(
                        'Missing value',
                        static::getName(),
                        $name
                    );
                } else {
                    continue;
                }
            }

            $this->assertInitVariableType($name, $variables[$name], $variableConfig['type']);
        }

        $this->initVariables = [];

        foreach ($variables as $name => $value) {
            $this->initSessionVariable($name, $value);

            $this->initVariables[$name] = $this->initVariableToStorageValue($name, $value);
        }

        if ($this->session) {
            $this->saveInitVariables();
        }
    }

    /**
     * Rebuild the opening values from what the session kept, for the times
     * there is no request to read them from — an asynchronous event resuming a
     * flow, typically.
     */
    public function autoInitVariables(array $storedValues): void
    {
        $config = $this->getInitVariablesConfig();
        $variables = [];

        foreach ($storedValues as $name => $value) {
            $variables[$name] = isset($config[$name]['autoInit'])
                ? $config[$name]['autoInit']($value)
                : $value;
        }

        $this->setInitialVariables($variables);
    }

    /**
     * @return array<string, mixed>
     */
    public function getInitVariables(): array
    {
        return $this->initVariables;
    }

    public function saveInitVariables(): void
    {
        foreach ($this->initVariables as $name => $storageValue) {
            if ($this->getVariableValue($name) === null) {
                $this->setVariableValue($name, $storageValue, initial: true);
            }
        }
    }

    /**
     * Among the cursors whose route params match the request, the one the
     * visitor actually means.
     *
     * @param TunnelCursor[] $candidates
     * @param array|null     $options the options read from the query string, if any
     */
    public function selectCurrentCursor(
        array $candidates,
        ?array $options = null,
    ): ?TunnelCursor {
        $filtered = array_filter(
            $candidates,
            fn (TunnelCursor $cursor): bool => $this->isCurrentCursorCandidate($cursor, $options)
        );

        if (!$filtered) {
            return null;
        }

        if (count($filtered) === 1) {
            return current($filtered);
        }

        return $this->selectClosestCursorFromRoot($filtered);
    }

    public function isCurrentCursorCandidate(
        TunnelCursor $cursor,
        ?array $options = null,
    ): bool {
        $session = $this->requireSession();

        if (!$cursor->step->isCurrentCursorCandidate($cursor, $session, $options)) {
            return false;
        }

        if ($options !== null && !$cursor->stepMatch($cursor->step, $options)) {
            return false;
        }

        $lastAccessedCursor = $session->getLastAccessedCursorHash()
            ? $this->getCursor($session->getLastAccessedCursorHash())
            : null;

        if (!$lastAccessedCursor) {
            return true;
        }

        // The path already walked said which variant of this step it leads to.
        if ($lastAccessedCursor->findFirstNextByStep($cursor->step)) {
            $redirectsToHash = $this->getVariableValue(
                TunnelCursor::VARIABLE_NAME_REDIRECTS_TO,
                $lastAccessedCursor
            );

            if ($redirectsToHash && $redirectsToHash !== $cursor->hash) {
                return false;
            }
        }

        return $lastAccessedCursor->isOnSamePath($cursor);
    }

    /**
     * @param TunnelCursor[] $cursors
     */
    public function selectClosestCursorFromRoot(array $cursors): ?TunnelCursor
    {
        $closestCursor = null;

        foreach ($cursors as $cursor) {
            if (!$closestCursor || $cursor->distanceFromRoot() < $closestCursor->distanceFromRoot()) {
                $closestCursor = $cursor;
            }
        }

        return $closestCursor;
    }

    public function needsGlobalRedirect(TunnelCursor $cursor): null|RedirectResponse|TunnelCursor
    {
        return $cursor->step->needsRedirect($cursor);
    }

    /**
     * @return TunnelCursor[]
     */
    public function findCursorsByStep(string|AbstractTunnelStep $step): array
    {
        return array_values(
            array_filter(
                $this->cursors,
                static fn (TunnelCursor $cursor): bool => $cursor->stepMatch($step)
            )
        );
    }

    public function buildViewParams(TunnelCursor $cursor): array
    {
        return [];
    }

    public function buildRouteParams(TunnelCursor $cursor): array
    {
        return [];
    }

    /**
     * Let the tunnel keep a typed hold on one of its opening values, rather
     * than read it back from the session every time.
     */
    protected function initSessionVariable(
        string $name,
        mixed $value,
    ): void {
        // To override.
    }

    /**
     * Entities are stored as their identifier: a session variable holds data,
     * and the `autoInit` callable of the config is what loads them back.
     */
    protected function initVariableToStorageValue(
        string $name,
        mixed $value,
    ): mixed {
        if ($value instanceof AbstractEntity) {
            $id = $value->getId();

            return $id instanceof Uuid ? $id->toRfc4122() : $id;
        }

        return $value;
    }

    private function assertInitVariableType(
        string $name,
        mixed $value,
        string $expectedType,
    ): void {
        $type = is_object($value) ? $value::class : gettype($value);

        if ($type === $expectedType || $value instanceof $expectedType) {
            return;
        }

        throw new TunnelInitVariableException(
            'Type mismatch, expected "' . $expectedType . '", got "' . $type . '"',
            static::getName(),
            $name
        );
    }

    protected function requireSession(): TunnelSession
    {
        if (!$this->session) {
            throw new LogicException(
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
