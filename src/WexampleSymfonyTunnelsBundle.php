<?php

namespace Wexample\SymfonyTunnels;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyPseudocode\Interface\PseudocodeBundleInterface;
use Wexample\SymfonyTunnels\DependencyInjection\Compiler\TunnelControllersCompilerPass;

class WexampleSymfonyTunnelsBundle extends AbstractBundle implements PseudocodeBundleInterface
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TunnelControllersCompilerPass());
    }

    public static function getPseudocodeSourcePaths(): array
    {
        return [__DIR__ . '/'];
    }
}
