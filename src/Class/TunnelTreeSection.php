<?php

namespace Wexample\SymfonyTunnels\Class;

/**
 * One row of the tunnel map: a step with given options, and the cursor standing
 * for it on each path that goes through it.
 */
class TunnelTreeSection
{
    /**
     * @param array<string, TunnelCursor> $pathCursors keyed by path index
     */
    public function __construct(
        public readonly TunnelCursor $cursor,
        public array $pathCursors = [],
    ) {
    }
}
