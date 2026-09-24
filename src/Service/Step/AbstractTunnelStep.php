<?php

namespace Wexample\SymfonyTunnels\Service\Step;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Wexample\SymfonyTranslations\Translation\Translator;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Enum\TunnelSessionStatus;
use Wexample\SymfonyTunnels\Enum\TunnelStepCompleteStrategy;
use Wexample\SymfonyTunnels\Enum\TunnelStepPreviousLoadingStrategy;
use Wexample\SymfonyTunnels\Enum\TunnelStepRedirectStrategy;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;

/**
 * One step of a tunnel, as a service shared by every tunnel using it.
 *
 * A step holds no state of its own: everything it needs about the flow in
 * progress comes from the cursor it is handed, which knows its manager and its
 * place in the tree.
 */
abstract class AbstractTunnelStep
{
    abstract public static function getName(): string;

    /**
     * Build the cursor for this step under $previous, and the whole subtree
     * below it.
     */
    public function createCursor(
        AbstractTunnelManagerService $manager,
        array $options = [],
        ?TunnelCursor $previous = null,
        ?string $name = null,
    ): TunnelCursor {
        $hash = TunnelCursor::buildCursorHash($manager, $this, $previous, $options);

        if ($cursor = $manager->getCursor($hash)) {
            return $cursor;
        }

        $cursor = new TunnelCursor($this, $manager, $options, $previous, $name);

        $this->forEachAllowedNextStep(
            static function (
                AbstractTunnelStep $nextStep,
                array $nextOptions,
                ?string $nextName
            ) use ($cursor): void {
                $cursor->addNext(
                    $nextStep->createCursor(
                        $cursor->manager,
                        $nextOptions,
                        $cursor,
                        $nextName
                    )
                );
            },
            $cursor
        );

        return $cursor;
    }

    /**
     * The steps allowed after this one, each entry being either a step service
     * or `['step' => $step, 'options' => [], 'name' => null]`.
     */
    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [];
    }

    /**
     * Every ancestor gets a say on what may follow one of its descendants, which
     * is how a branch narrows the flow it opened.
     */
    public function alterNextStepAllowedFollowings(
        AbstractTunnelStep $nextStep,
        array $allowed,
        TunnelCursor $cursor,
    ): array {
        return $allowed;
    }

    public function forEachAllowedNextStep(
        callable $callback,
        TunnelCursor $cursor,
    ): void {
        $allowed = $this->getAllowedNextSteps($cursor);

        $cursor->forEachPreviousRecursive(
            function (TunnelCursor $previousCursor) use (&$allowed): void {
                $allowed = $previousCursor->step->alterNextStepAllowedFollowings(
                    $this,
                    $allowed,
                    $previousCursor
                );
            }
        );

        foreach ($allowed as $nextStep) {
            $nextOptions = [];
            $nextName = null;

            if (is_array($nextStep)) {
                $nextOptions = $nextStep['options'] ?? [];
                $nextName = $nextStep['name'] ?? null;
                $nextStep = $nextStep['step'];
            }

            $callback($nextStep, $nextOptions, $nextName);
        }
    }

    public function completeStrategy(): TunnelStepCompleteStrategy
    {
        return TunnelStepCompleteStrategy::ON_INIT;
    }

    /**
     * How long the session lives once the visitor stopped on this step: a step
     * holding a stock for them asks for minutes, not a day.
     *
     * @return string a relative format, as `15 minutes` or `1 day`
     */
    public function getSessionExpiration(TunnelCursor $cursor): string
    {
        return $cursor->manager->getSessionExpiration();
    }

    public function previousStepLoadingStrategy(): TunnelStepPreviousLoadingStrategy
    {
        return TunnelStepPreviousLoadingStrategy::RESET;
    }

    public function needsRedirectDefaultStrategy(): TunnelStepRedirectStrategy
    {
        return TunnelStepRedirectStrategy::ANY_PREVIOUS_INCOMPLETE;
    }

    /**
     * Where the visitor should be sent instead of here, if anywhere: a response
     * to leave the tunnel, or a cursor to move within it.
     */
    public function needsRedirect(TunnelCursor $cursor): null|RedirectResponse|TunnelCursor
    {
        if ($cursor->previous && ! $cursor->previous->isComplete()) {
            // The direct parent is incomplete on purpose: it asked this very
            // step to be the one completing it.
            if ($cursor->previous->step->completeStrategy() === TunnelStepCompleteStrategy::ON_NEXT_INIT) {
                return null;
            }

            return $cursor->previous;
        }

        if ($this->needsRedirectDefaultStrategy() === TunnelStepRedirectStrategy::ANY_PREVIOUS_INCOMPLETE) {
            return $cursor->getFirstIncompletePreviousCursor();
        }

        return null;
    }

    /**
     * A cursor placed between two others can forbid moving from one to the
     * other, which is how a payment page keeps the visitor from going back.
     */
    public function allowAccessOf(
        TunnelCursor $cursor,
        TunnelCursor $siblingCursor,
    ): bool {
        return true;
    }

    /**
     * Whether a link from $cursorFrom to $cursor may be offered at all.
     */
    public function allowDirectAccess(
        TunnelCursor $cursor,
        TunnelCursor $cursorFrom,
    ): bool {
        // A step that would start the session over, a closed one by default,
        // only leads back to the entrypoint of a new session.
        $session = $cursor->manager->getSession();

        if ($session && $cursor !== $cursorFrom && $cursor->step->tunnelSessionRecreate($session, $cursor)) {
            return false;
        }

        $allowed = true;

        $cursorFrom->forEachCursorBetween(
            $cursor,
            static function (TunnelCursor $cursorBetween) use ($cursor, &$allowed): void {
                if ($allowed && ! $cursorBetween->step->allowAccessOf($cursorBetween, $cursor)) {
                    $allowed = false;
                }
            }
        );

        if (! $allowed) {
            return false;
        }

        if (! $cursorFrom->hasNextRecursive($cursor)) {
            return true;
        }

        // Moving forward requires every step up to the target to be done, or
        // the link would only lead to a redirect back.
        return $cursor->getFirstIncompletePreviousCursor() === null;
    }

    /**
     * Make this cursor the one being displayed, which is where the completion
     * and reset rules of the whole path apply.
     */
    public function initAsCurrentStep(TunnelCursor $cursor): void
    {
        $cursor->manager->updateLastAccessedCursor($cursor);

        if ($cursor->previous) {
            $this->notifyPreviousCursors($cursor);

            if ($cursor->previous->step->completeStrategy() === TunnelStepCompleteStrategy::ON_NEXT_INIT) {
                $cursor->previous->setComplete($cursor);
            }
        }

        // Coming back to a step that had already sent the visitor somewhere:
        // whatever that branch produced no longer holds.
        if ($abandonedCursor = $cursor->getRedirectsToCursor()) {
            $cursor->removeVariable(TunnelCursor::VARIABLE_NAME_REDIRECTS_TO);

            $abandonedCursor->step->onPreviousStepLoading($abandonedCursor, $cursor, 1);
        }

        $this->applyCompleteStrategy($cursor);
    }

    public function onNextStepLoading(TunnelCursor $cursor): void
    {
        // To override.
    }

    public function onPreviousStepLoading(
        TunnelCursor $cursor,
        TunnelCursor $loadedPreviousCursor,
        int $distance,
    ): void {
        if ($this->previousStepLoadingStrategy() === TunnelStepPreviousLoadingStrategy::RESET) {
            $this->reset($cursor);
        }

        foreach ($cursor->next as $next) {
            $next->step->onPreviousStepLoading($next, $loadedPreviousCursor, $distance + 1);
        }
    }

    public function onSessionDestroy(TunnelSession $session): void
    {
        // To override.
    }

    public function reset(TunnelCursor $cursor): void
    {
        $cursor->manager->resetCursor($cursor);
    }

    /**
     * Which of the following cursors the visitor goes to once done here.
     */
    public function selectNextCursor(TunnelCursor $cursor): ?TunnelCursor
    {
        return $cursor->findFirstNext();
    }

    /**
     * A last word on whether this cursor may be the one the request is about,
     * once the route params and the options have already matched.
     */
    public function isCurrentCursorCandidate(
        TunnelCursor $cursor,
        TunnelSession $session,
        ?array $options = null,
    ): bool {
        return true;
    }

    /**
     * Whether reaching this cursor should start a session over rather than
     * continue the one at hand.
     */
    public function tunnelSessionRecreate(
        TunnelSession $session,
        TunnelCursor $cursor,
    ): bool {
        return $session->hasStatus(TunnelSessionStatus::COMPLETED);
    }

    public function buildViewParams(TunnelCursor $cursor): array
    {
        return [];
    }

    public function buildRouteParams(TunnelCursor $cursor): array
    {
        return [
            'step' => static::getName(),
        ];
    }

    /**
     * Cursors sharing an identifier are shown as one entry in the side list.
     * Steps whose every option makes a different page override it.
     */
    public function getPathGroupIdentifier(TunnelCursor $cursor): string
    {
        return static::getName();
    }

    /**
     * What the visitor reads for this step in the tunnel navigation.
     */
    public function buildLabel(TunnelCursor $cursor): string
    {
        return ucfirst(str_replace('-', ' ', static::getName()));
    }

    /**
     * What the navigation reads where several cursors of this step share a
     * place, the visitor having yet to pick one: their label when they agree,
     * the name of the step when they do not.
     *
     * @param TunnelCursor[] $cursors
     */
    public function buildGroupLabel(array $cursors): string
    {
        $labels = array_unique(
            array_map(fn (TunnelCursor $cursor): string => $this->buildLabel($cursor), $cursors)
        );

        return count($labels) === 1
            ? reset($labels)
            : ucfirst(str_replace('-', ' ', static::getName()));
    }

    public function getViewFolder(TunnelCursor $cursor): string
    {
        return $cursor->manager::getName();
    }

    /**
     * The template of the step, relative to the front directory of whoever
     * renders it and without extension: `tunnels/<tunnel>/<step>`.
     */
    public function buildStepView(TunnelCursor $cursor): string
    {
        return 'tunnels/' . $this->getViewFolder($cursor) . '/' . static::getName();
    }

    /**
     * Derived from the view path, so that a step's texts sit next to its
     * template the way every other page of the suite does.
     */
    public function buildTranslationDomain(TunnelCursor $cursor): string
    {
        return Translator::buildDomainFromTemplatePath(
            $this->buildStepView($cursor)
        );
    }

    public function buildTranslationKey(
        TunnelCursor $cursor,
        string $key,
    ): string {
        return $this->buildTranslationDomain($cursor) . Translator::DOMAIN_SEPARATOR . $key;
    }

    /**
     * Tell every ancestor that a descendant is being displayed, and prune the
     * branch an already-complete ancestor had sent the visitor to.
     */
    private function notifyPreviousCursors(TunnelCursor $cursor): void
    {
        $previousNext = $cursor;

        $cursor->forEachPreviousRecursive(
            function (TunnelCursor $siblingCursor) use ($cursor, &$previousNext): void {
                $abandonedCursor = $siblingCursor->isComplete()
                    ? $siblingCursor->getRedirectsToCursor()
                    : null;

                if ($abandonedCursor && $abandonedCursor !== $previousNext) {
                    $abandonedCursor->step->onPreviousStepLoading($abandonedCursor, $cursor, 1);
                }

                $siblingCursor->step->onNextStepLoading($cursor);

                $previousNext = $siblingCursor;
            }
        );
    }

    private function applyCompleteStrategy(TunnelCursor $cursor): void
    {
        $strategy = $this->completeStrategy();

        if ($strategy === TunnelStepCompleteStrategy::MANUAL) {
            return;
        }

        if ($strategy === TunnelStepCompleteStrategy::ON_INIT) {
            // A single following cursor is also the branch that was taken.
            $cursor->setComplete(
                count($cursor->next) === 1 ? $cursor->findFirstNext() : null
            );

            return;
        }

        $cursor->unsetComplete();
    }
}
