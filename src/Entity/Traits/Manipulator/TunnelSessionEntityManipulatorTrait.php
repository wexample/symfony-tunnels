<?php

namespace Wexample\SymfonyTunnels\Entity\Traits\Manipulator;

use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;
use Wexample\SymfonyTunnels\Entity\TunnelSession;

trait TunnelSessionEntityManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return TunnelSession::class;
    }
}
