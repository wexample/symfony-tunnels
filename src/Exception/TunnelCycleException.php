<?php

namespace Wexample\SymfonyTunnels\Exception;

use LogicException;
use Wexample\SymfonyTunnels\Class\TunnelCursor;

/**
 * A step declared itself, with the same options, among what may follow it. The
 * tree would never finish building.
 */
class TunnelCycleException extends LogicException
{
    public function __construct(TunnelCursor $cursor)
    {
        parent::__construct(
            'The step ' . $cursor->step::getName()
            . ' of tunnel ' . $cursor->manager::getName()
            . ' is reachable from itself: ' . $cursor
        );
    }
}
