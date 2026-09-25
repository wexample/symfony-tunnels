<?php

namespace Wexample\SymfonyTunnels\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Class\TunnelNavigationItem;
use Wexample\SymfonyTunnels\Helper\TunnelTreeHelper;
use Wexample\SymfonyTunnels\Service\TunnelRegistry;
use Wexample\SymfonyTunnels\Service\TunnelRoutingService;

class TunnelExtension extends AbstractExtension
{
    public function __construct(
        private readonly TunnelRoutingService $tunnelRoutingService,
        private readonly TunnelRegistry $tunnelRegistry,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tunnel_cursor_url', $this->tunnelCursorUrl(...)),
            new TwigFunction('tunnel_entrypoint_url', $this->tunnelEntrypointUrl(...)),
            new TwigFunction('tunnel_stepper', $this->tunnelStepper(...)),
            new TwigFunction('tunnel_timeline', $this->tunnelTimeline(...)),
            new TwigFunction('tunnel_previous_url', $this->tunnelPreviousUrl(...)),
            new TwigFunction('tunnel_next_url', $this->tunnelNextUrl(...)),
        ];
    }

    public function tunnelCursorUrl(
        TunnelCursor $cursor,
        array $params = [],
    ): string {
        return $this->tunnelRoutingService->buildCursorUrl($cursor, $params);
    }

    /**
     * Where a link into a tunnel points from outside of it: its first step,
     * the tunnel sending the visitor on from there if they have walked it
     * further.
     *
     * @param string $nameOrClass the tunnel name, or its manager class
     */
    public function tunnelEntrypointUrl(string $nameOrClass): string
    {
        $tunnel = $this->tunnelRegistry->getTunnel($nameOrClass)
            ?? throw new \InvalidArgumentException(sprintf('No tunnel is named "%s".', $nameOrClass));

        return $this->tunnelRoutingService->buildCursorUrl($tunnel->createEntrypoint());
    }

    /**
     * The options of the design system stepper for the step being displayed:
     * the steps known behind and ahead, a link on those the visitor may reach.
     *
     * @return array{steps: array<array{label?: string, href?: ?string, unknown?: bool}>, current: int}
     */
    public function tunnelStepper(TunnelCursor $cursor): array
    {
        $items = TunnelTreeHelper::buildKnownNextSteps(
            $cursor->manager->getEntrypointCursor(),
            $cursor
        );

        $currentGroup = $cursor->step->getPathGroupIdentifier($cursor);
        $steps = [];
        $current = 0;

        foreach ($items as $index => $item) {
            if ($item?->groupIdentifier === $currentGroup) {
                $current = $index;
            }

            $steps[] = $this->buildStepperStep($item);
        }

        return [
            'steps' => $steps,
            'current' => $current,
        ];
    }

    /**
     * The options of the design system timeline for the path walked up to the
     * step being displayed. In a tree the way to a cursor is unique: it is the
     * line of its ancestors.
     *
     * @return array{numbered: bool, items: array<array{title: string, text?: string, state?: string}>}
     */
    public function tunnelTimeline(TunnelCursor $cursor): array
    {
        $items = [];

        foreach ([...$cursor->getPreviousTrace(), $cursor] as $walked) {
            $item = [
                'title' => $walked->step->buildLabel($walked),
            ];

            if (null !== $summary = $walked->step->buildSummary($walked)) {
                $item['text'] = $summary;
            }

            if ($walked === $cursor) {
                $item['state'] = 'current';
            } elseif ($walked->isComplete()) {
                $item['state'] = 'done';
            }

            $items[] = $item;
        }

        return [
            'numbered' => true,
            'items' => $items,
        ];
    }

    public function tunnelPreviousUrl(TunnelCursor $cursor): ?string
    {
        $previous = $cursor->previous;

        if (! $previous || ! $previous->step->allowDirectAccess($previous, $cursor)) {
            return null;
        }

        return $this->tunnelRoutingService->buildCursorUrl($previous);
    }

    public function tunnelNextUrl(TunnelCursor $cursor): ?string
    {
        // Where the tree parts, no branch is "the next one": the step shows the
        // branches itself, and a next button could only pick one of them behind
        // the visitor's back.
        if (count($cursor->next) > 1) {
            return null;
        }

        $next = $cursor->manager->selectNextCursor($cursor);

        if (! $next || ! $next->step->allowDirectAccess($next, $cursor)) {
            return null;
        }

        return $this->tunnelRoutingService->buildCursorUrl($next);
    }

    /**
     * A null item stands for the steps ahead that depend on the branch the
     * visitor takes, which the stepper draws as an unnumbered gap.
     *
     * @return array{label?: string, href?: ?string, unknown?: bool}
     */
    private function buildStepperStep(?TunnelNavigationItem $item): array
    {
        if (! $item) {
            return ['unknown' => true];
        }

        return [
            'label' => $item->lastCursor->step->buildGroupLabel($item->cursors ?: [$item->lastCursor]),
            'href' => $item->linkCursor
                ? $this->tunnelRoutingService->buildCursorUrl($item->linkCursor)
                : null,
        ];
    }
}
