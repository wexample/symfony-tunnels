<?php

namespace Wexample\SymfonyTunnels\Class;

use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyTunnels\Enum\TunnelCursorPosition;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

/**
 * One node of the tunnel tree: a step reached by one precise path, with the
 * options that path gave it. The same step service appears as many times as
 * there are ways to reach it, each time as a distinct cursor.
 */
class TunnelCursor
{
    public const string QUERY_STRING_CURSOR_OPTIONS = 'cursor-options';

    public const string VARIABLE_NAME_COMPLETE = 'tunnel-step-complete';

    public const string VARIABLE_NAME_REDIRECTS_TO = 'redirects-to';

    public readonly string $hash;

    public readonly string $name;

    /**
     * @var array<string, TunnelCursor>
     */
    public array $next = [];

    public function __construct(
        public readonly AbstractTunnelStep $step,
        public readonly AbstractTunnelManagerService $manager,
        public readonly array $options = [],
        public readonly ?TunnelCursor $previous = null,
        ?string $name = null,
    ) {
        $this->hash = static::buildCursorHash(
            $manager,
            $step,
            $previous,
            $options
        );

        $this->name = $name ?: $this->hash;

        $manager->addCursor($this);
    }

    /**
     * Stable across requests, which is what makes it usable as the database key
     * of everything stored under the cursor.
     */
    public static function buildCursorHash(
        string|AbstractTunnelManagerService $manager,
        string|AbstractTunnelStep $step,
        ?TunnelCursor $previous = null,
        array $options = [],
    ): string {
        return md5(
            ClassHelper::getClassPath($manager)
            . ClassHelper::getClassPath($step)
            . ($previous?->hash ?: '')
            . serialize($options)
        );
    }

    public function __toString(): string
    {
        return $this->hash
            . ' ' . $this->step::getName()
            . ' ' . json_encode($this->options);
    }

    public function addNext(TunnelCursor $cursor): void
    {
        $this->next[$cursor->hash] = $cursor;
    }

    public function isFirst(): bool
    {
        return !$this->previous;
    }

    public function isLast(): bool
    {
        return empty($this->next);
    }

    public function distanceFromRoot(): int
    {
        return $this->previous ? 1 + $this->previous->distanceFromRoot() : 0;
    }

    /**
     * A hash ignoring the path followed to reach the cursor, so that two cursors
     * of the same step and options compare equal wherever they sit.
     */
    public function buildRelativeHash(): string
    {
        return static::buildCursorHash(
            $this->manager,
            $this->step,
            null,
            $this->options
        );
    }

    public function getPosition(?TunnelCursor $relatedTo = null): TunnelCursorPosition
    {
        $relatedTo = $relatedTo ?: $this->manager->getCurrentCursor();

        if ($relatedTo === $this) {
            return TunnelCursorPosition::SAME;
        }

        if ($relatedTo->hasPreviousRecursive($this)) {
            return TunnelCursorPosition::PREVIOUS;
        }

        if ($relatedTo->hasNextRecursive($this)) {
            return TunnelCursorPosition::NEXT;
        }

        return TunnelCursorPosition::UNRELATED;
    }

    public function isOnSamePath(TunnelCursor $cursor): bool
    {
        return $this === $cursor
            || $this->hasPreviousRecursive($cursor)
            || $this->hasNextRecursive($cursor);
    }

    public function hasPreviousRecursive(TunnelCursor $relatedCursor): bool
    {
        if (!$this->previous) {
            return false;
        }

        return $this->previous === $relatedCursor
            || $this->previous->hasPreviousRecursive($relatedCursor);
    }

    public function hasNext(
        TunnelCursor $cursor,
        bool $recursive = false,
    ): bool {
        foreach ($this->next as $next) {
            if ($next === $cursor) {
                return true;
            }

            if ($recursive && $next->hasNext($cursor, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasNextRecursive(TunnelCursor $relatedCursor): bool
    {
        return $this->hasNext($relatedCursor, true);
    }

    /**
     * Walk the cursors separating this one from $cursorLimit, excluded, in
     * whichever direction the limit sits.
     */
    public function forEachCursorBetween(
        TunnelCursor $cursorLimit,
        callable $callback,
    ): void {
        $limitReached = false;

        $collector = function (TunnelCursor $cursor) use (
            $cursorLimit,
            &$limitReached,
            $callback
        ): void {
            if ($cursor === $cursorLimit) {
                $limitReached = true;
            }

            if (!$limitReached) {
                $callback($cursor);
            }
        };

        if ($this->hasPreviousRecursive($cursorLimit)) {
            $this->forEachPreviousRecursive($collector);
        } elseif ($this->hasNextRecursive($cursorLimit)) {
            $this->forEachNextRecursive($collector);
        }
    }

    /**
     * @return TunnelCursor[] from the root down to the direct parent
     */
    public function getPreviousTrace(): array
    {
        if (!$this->previous) {
            return [];
        }

        $trace = $this->previous->getPreviousTrace();
        $trace[] = $this->previous;

        return $trace;
    }

    public function forSelfAndPreviousRecursive(
        callable $callback,
        bool $lastFirst = false,
    ): void {
        if (!$lastFirst) {
            $callback($this);
        }

        $this->forEachPreviousRecursive($callback, $lastFirst);

        if ($lastFirst) {
            $callback($this);
        }
    }

    public function forEachPreviousRecursive(
        callable $callback,
        bool $lastFirst = false,
    ): void {
        $this->previous?->forSelfAndPreviousRecursive($callback, $lastFirst);
    }

    public function forSelfAndNextRecursive(
        callable $callback,
        bool $lastFirst = false,
    ): void {
        if (!$lastFirst) {
            $callback($this);
        }

        $this->forEachNextRecursive($callback, $lastFirst);

        if ($lastFirst) {
            $callback($this);
        }
    }

    public function forEachNextRecursive(
        callable $callback,
        bool $lastFirst = false,
    ): void {
        foreach ($this->next as $next) {
            $next->forSelfAndNextRecursive($callback, $lastFirst);
        }
    }

    public function findFirstNext(): ?TunnelCursor
    {
        return current($this->next) ?: null;
    }

    public function findFirstNextByName(string $name): ?TunnelCursor
    {
        foreach ($this->next as $next) {
            if ($next->name === $name) {
                return $next;
            }
        }

        return null;
    }

    /**
     * @param array $options every one of them must be carried by the cursor
     */
    public function findFirstNextByOptions(array $options): ?TunnelCursor
    {
        foreach ($this->next as $next) {
            if ($next->hasOptions($options)) {
                return $next;
            }
        }

        return null;
    }

    public function findFirstNextByStep(
        string|AbstractTunnelStep $nameOrClassName,
        ?array $options = null,
    ): ?TunnelCursor {
        foreach ($this->next as $next) {
            if ($next->stepMatch($nameOrClassName, $options)) {
                return $next;
            }
        }

        return null;
    }

    public function stepMatch(
        string|AbstractTunnelStep $nameOrClassName,
        ?array $options = null,
    ): bool {
        if ($nameOrClassName instanceof AbstractTunnelStep) {
            $stepName = $nameOrClassName::getName();
        } else {
            $stepName = class_exists($nameOrClassName)
                ? $nameOrClassName::getName()
                : $nameOrClassName;
        }

        return $this->step::getName() === $stepName
            && ($options === null || $this->hasSameOptions($options));
    }

    /**
     * True when the cursor carries exactly these options, values included.
     */
    public function hasSameOptions(array $options): bool
    {
        if (count($this->options) !== count($options)) {
            return false;
        }

        return $this->hasOptions($options);
    }

    /**
     * True when the cursor carries at least these options, values included.
     */
    public function hasOptions(array $options): bool
    {
        foreach ($options as $name => $value) {
            if (!array_key_exists($name, $this->options) || $this->options[$name] !== $value) {
                return false;
            }
        }

        return true;
    }

    public function getVariableValue(
        string $name,
        mixed $default = null,
    ): mixed {
        return $this->manager->getVariableValue($name, $this, $default);
    }

    public function setVariableValue(
        string $name,
        mixed $value,
    ): void {
        $this->manager->setVariableValue($name, $value, $this);
    }

    public function removeVariable(string $name): bool
    {
        return $this->manager->removeVariable($name, $this);
    }

    public function isComplete(): bool
    {
        return (bool) $this->getVariableValue(self::VARIABLE_NAME_COMPLETE);
    }

    public function setComplete(?TunnelCursor $redirectsTo = null): void
    {
        if ($redirectsTo) {
            $this->setVariableValue(
                self::VARIABLE_NAME_REDIRECTS_TO,
                $redirectsTo->hash
            );
        }

        $this->setVariableValue(self::VARIABLE_NAME_COMPLETE, true);
    }

    public function unsetComplete(): void
    {
        $this->removeVariable(self::VARIABLE_NAME_COMPLETE);
    }

    /**
     * The branch this cursor sent the visitor to, remembered so that coming back
     * knows what to invalidate.
     */
    public function getRedirectsToCursor(): ?TunnelCursor
    {
        $hash = $this->getVariableValue(self::VARIABLE_NAME_REDIRECTS_TO);

        return $hash ? $this->manager->getCursor($hash) : null;
    }

    public function hasAllPreviousComplete(): bool
    {
        if (!$this->previous) {
            return true;
        }

        return $this->previous->isComplete()
            && $this->previous->hasAllPreviousComplete();
    }

    public function hasPreviousComplete(): bool
    {
        return (bool) $this->previous?->isComplete();
    }

    /**
     * The closest ancestor the visitor still has to go through, if any.
     */
    public function getFirstIncompletePreviousCursor(): ?TunnelCursor
    {
        $found = null;

        $this->forEachPreviousRecursive(
            function (TunnelCursor $previous) use (&$found): void {
                if (!$found && !$previous->isComplete()) {
                    $found = $previous;
                }
            },
            true
        );

        return $found;
    }

    public function getNextCursor(): ?TunnelCursor
    {
        return $this->manager->selectNextCursor($this);
    }

    /**
     * Drop what this cursor and everything below it had stored.
     */
    public function resetPath(): void
    {
        $this->forSelfAndNextRecursive(
            static function (TunnelCursor $cursor): void {
                $cursor->step->reset($cursor);
            },
            true
        );
    }

    public function getTitleTranslationKey(): string
    {
        return $this->step->buildTranslationKey($this, 'title');
    }
}
