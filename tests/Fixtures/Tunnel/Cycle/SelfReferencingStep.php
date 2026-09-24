<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Cycle;

use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

/**
 * Declares itself among what may follow it, which is the mistake the tree
 * building has to catch rather than loop on.
 */
class SelfReferencingStep extends AbstractTunnelStep
{
    public static function getName(): string
    {
        return 'self-referencing';
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [$this];
    }
}
