<?php

namespace Wexample\SymfonyTunnels\Helper;

use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Class\TunnelNavigationItem;
use Wexample\SymfonyTunnels\Class\TunnelTreeSection;

/**
 * Reading a built tree as the visitor and the developer need to see it: the
 * paths through it, the map of its sections, the steps known ahead.
 */
class TunnelTreeHelper
{
    public const string PATH_INDEX_ROOT = 'root';

    /**
     * Every root-to-leaf path of the tree.
     *
     * @return array<string, TunnelCursor[]> keyed by a path index stable for a given tree
     */
    public static function buildPaths(TunnelCursor $entrypoint): array
    {
        $paths = [];
        self::collectPaths($entrypoint, [], self::PATH_INDEX_ROOT, $paths);

        return $paths;
    }

    /**
     * The rows of the tunnel map, one per step and options, in an order every
     * path agrees with: a step shared by two paths shows once.
     *
     * @return TunnelTreeSection[]
     */
    public static function buildSections(TunnelCursor $entrypoint): array
    {
        /** @var array<string, TunnelTreeSection> $sections */
        $sections = [];
        $order = [];

        foreach (self::buildPaths($entrypoint) as $pathIndex => $path) {
            // The relative hash ignores the path, so the same step with the
            // same options shares a row wherever it sits.
            $pathHashes = array_map(
                static fn (TunnelCursor $cursor): string => $cursor->buildRelativeHash(),
                $path
            );

            foreach ($path as $position => $cursor) {
                $relativeHash = $pathHashes[$position];

                if (!isset($sections[$relativeHash])) {
                    $sections[$relativeHash] = new TunnelTreeSection($cursor);

                    // Right before the next row this path already has, which
                    // keeps it after the previous one and after its siblings.
                    $insertAt = count($order);
                    foreach (array_slice($pathHashes, $position + 1) as $nextHash) {
                        if (isset($sections[$nextHash])) {
                            $insertAt = array_search($nextHash, $order, true);
                            break;
                        }
                    }

                    array_splice($order, $insertAt, 0, [$relativeHash]);
                }

                $sections[$relativeHash]->pathCursors[$pathIndex] = $cursor;
            }
        }

        return array_map(
            static fn (string $relativeHash): TunnelTreeSection => $sections[$relativeHash],
            $order
        );
    }

    /**
     * The steps the visitor is bound to go through, before and after where they
     * stand, whatever branch they take. A null entry marks where the remaining
     * paths part ways.
     *
     * @return array<TunnelNavigationItem|null>
     */
    public static function buildKnownNextSteps(
        TunnelCursor $entrypoint,
        ?TunnelCursor $currentCursor = null,
    ): array {
        $paths = self::buildPaths($entrypoint);

        if ($currentCursor) {
            $paths = array_filter(
                $paths,
                static fn (array $path): bool => in_array($currentCursor, $path, true)
            );
        }

        $groups = [];

        foreach ($paths as $pathIndex => $path) {
            foreach ($path as $depth => $cursor) {
                $key = $cursor->step->getPathGroupIdentifier($cursor);

                $groups[$key] ??= [
                    'cursors' => [],
                    'depth' => 0,
                ];

                $groups[$key]['cursors'][$pathIndex] = $cursor;
                $groups[$key]['depth'] = max($depth, $groups[$key]['depth']);
            }
        }

        uasort(
            $groups,
            static fn (array $a, array $b): int => $a['depth'] <=> $b['depth']
        );

        $pathsCount = count($paths);
        $items = [];

        foreach ($groups as $key => $group) {
            if (count($group['cursors']) !== $pathsCount) {
                // Only some paths go through this step: its place is unknown.
                if ($items && end($items) !== null) {
                    $items[] = null;
                }

                continue;
            }

            $distinctCursors = [];
            foreach ($group['cursors'] as $cursor) {
                $distinctCursors[spl_object_id($cursor)] = $cursor;
            }

            $lastCursor = end($group['cursors']);
            $linkCursor = count($distinctCursors) === 1 ? $lastCursor : null;

            if ($linkCursor && (!$currentCursor || !$linkCursor->step->allowDirectAccess($linkCursor, $currentCursor))) {
                $linkCursor = null;
            }

            $items[] = new TunnelNavigationItem(
                (string) $key,
                $linkCursor,
                $lastCursor,
                $group['depth']
            );
        }

        return $items;
    }

    /**
     * @param TunnelCursor[]                  $pathBase the cursors above $cursor
     * @param array<string, TunnelCursor[]> $paths
     */
    private static function collectPaths(
        TunnelCursor $cursor,
        array $pathBase,
        string $pathIndex,
        array &$paths,
    ): void {
        $path = [...$pathBase, $cursor];

        if ($cursor->isLast()) {
            $paths[$pathIndex] = $path;

            return;
        }

        $first = true;

        foreach ($cursor->next as $next) {
            self::collectPaths(
                $next,
                $path,
                $first ? $pathIndex : $pathIndex . '-' . $next->hash,
                $paths
            );

            $first = false;
        }
    }
}
