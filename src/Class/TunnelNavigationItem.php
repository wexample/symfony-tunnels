<?php

namespace Wexample\SymfonyTunnels\Class;

/**
 * One entry of the list of steps a visitor can see ahead and behind: a group of
 * cursors every remaining path goes through.
 */
class TunnelNavigationItem
{
    public function __construct(
        public readonly string $groupIdentifier,
        /**
         * The cursor to link to, when the group comes down to a single cursor
         * the visitor is allowed to reach from where they stand.
         */
        public readonly ?TunnelCursor $linkCursor,
        /**
         * The cursor giving the entry its label and position.
         */
        public readonly TunnelCursor $lastCursor,
        public readonly int $depth,
    ) {
    }
}
