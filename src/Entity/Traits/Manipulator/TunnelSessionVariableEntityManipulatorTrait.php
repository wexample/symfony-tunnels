<?php

namespace Wexample\SymfonyTunnels\Entity\Traits\Manipulator;

use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;
use Wexample\SymfonyTunnels\Entity\TunnelSessionVariable;

trait TunnelSessionVariableEntityManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return TunnelSessionVariable::class;
    }
}
