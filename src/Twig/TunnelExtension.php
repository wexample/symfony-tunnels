<?php

namespace Wexample\SymfonyTunnels\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Class\TunnelNavigationItem;
use Wexample\SymfonyTunnels\Helper\TunnelTreeHelper;
use Wexample\SymfonyTunnels\Service\TunnelRoutingService;

class TunnelExtension extends AbstractExtension
{
    /**
     * Stands for the steps ahead that depend on the branch the visitor takes.
     */
    public const string LABEL_UNKNOWN_STEPS = '…';

    public function __construct(
        private readonly TunnelRoutingService $tunnelRoutingService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tunnel_cursor_url', $this->tunnelCursorUrl(...)),
            new TwigFunction('tunnel_stepper', $this->tunnelStepper(...)),
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
     * The options of the design system stepper for the step being displayed:
     * the steps known behind and ahead, a link on those the visitor may reach.
     *
     * @return array{steps: array<array{label: string, href: ?string}>, current: int}
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

    public function tunnelPreviousUrl(TunnelCursor $cursor): ?string
    {
        $previous = $cursor->previous;

        if (!$previous || !$previous->step->allowDirectAccess($previous, $cursor)) {
            return null;
        }

        return $this->tunnelRoutingService->buildCursorUrl($previous);
    }

    public function tunnelNextUrl(TunnelCursor $cursor): ?string
    {
        $next = $cursor->manager->selectNextCursor($cursor);

        if (!$next || !$next->step->allowDirectAccess($next, $cursor)) {
            return null;
        }

        return $this->tunnelRoutingService->buildCursorUrl($next);
    }

    /**
     * @return array{label: string, href: ?string}
     */
    private function buildStepperStep(?TunnelNavigationItem $item): array
    {
        if (!$item) {
            return [
                'label' => self::LABEL_UNKNOWN_STEPS,
                'href' => null,
            ];
        }

        return [
            'label' => $item->lastCursor->step->buildLabel($item->lastCursor),
            'href' => $item->linkCursor
                ? $this->tunnelRoutingService->buildCursorUrl($item->linkCursor)
                : null,
        ];
    }
}
