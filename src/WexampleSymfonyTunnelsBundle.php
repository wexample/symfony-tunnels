<?php

namespace Wexample\SymfonyTunnels;

use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyPseudocode\Interface\PseudocodeBundleInterface;

class WexampleSymfonyTunnelsBundle extends AbstractBundle implements PseudocodeBundleInterface
{
    public static function getPseudocodeSourcePaths(): array
    {
        return [__DIR__ . '/'];
    }
}
