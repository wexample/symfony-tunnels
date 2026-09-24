<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Cycle;

use Wexample\SymfonyTunnels\Interface\TunnelSessionStorageInterface;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

class CycleTunnelManagerService extends AbstractTunnelManagerService
{
    public function __construct(
        TunnelSessionStorageInterface $variableStorage,
        private readonly SelfReferencingStep $step,
    ) {
        parent::__construct($variableStorage);
    }

    public static function getName(): string
    {
        return 'cycle';
    }

    public function getEntrypointStep(): AbstractTunnelStep
    {
        return $this->step;
    }
}
