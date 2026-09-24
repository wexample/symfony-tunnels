<?php

namespace Wexample\SymfonyTunnels;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyHelpers\Interface\LoaderBundleInterface;
use Wexample\SymfonyPseudocode\Interface\PseudocodeBundleInterface;
use Wexample\SymfonyTunnels\DependencyInjection\Compiler\TunnelControllersCompilerPass;

class WexampleSymfonyTunnelsBundle extends AbstractBundle implements LoaderBundleInterface, PseudocodeBundleInterface
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new TunnelControllersCompilerPass());
    }

    public static function getLoaderFrontPaths(): array
    {
        return [
            BundleHelper::getBundleCssAlias(static::class) => __DIR__ . '/../assets/',
        ];
    }

    public static function getPseudocodeSourcePaths(): array
    {
        return [__DIR__ . '/'];
    }
}
