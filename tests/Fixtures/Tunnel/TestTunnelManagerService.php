<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel;

use Wexample\SymfonyTunnels\Interface\TunnelVariableStorageInterface;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;
use Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test\StepOne;

/**
 * The tunnel exercising every feature of the engine, ported from the one the
 * legacy code used as its executable specification.
 */
class TestTunnelManagerService extends AbstractTunnelManagerService
{
    public function __construct(
        TunnelVariableStorageInterface $variableStorage,
        private readonly StepOne $stepOne,
    ) {
        parent::__construct($variableStorage);
    }

    public static function getName(): string
    {
        return 'test';
    }

    public function getEntrypointStep(): AbstractTunnelStep
    {
        return $this->stepOne;
    }
}
