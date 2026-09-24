<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\FormTunnel;

use Wexample\SymfonyTunnels\Interface\TunnelSessionStorageInterface;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;

class FormTunnelManagerService extends AbstractTunnelManagerService
{
    public function __construct(
        TunnelSessionStorageInterface $sessionStorage,
        private readonly NameStep $nameStep,
    ) {
        parent::__construct($sessionStorage);
    }

    public static function getName(): string
    {
        return 'form';
    }

    public function getEntrypointStep(): AbstractTunnelStep
    {
        return $this->nameStep;
    }
}
