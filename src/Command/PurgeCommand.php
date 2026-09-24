<?php

namespace Wexample\SymfonyTunnels\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyTunnels\Service\TunnelRegistry;
use Wexample\SymfonyTunnels\WexampleSymfonyTunnelsBundle;

/**
 * Drops the expired sessions of every tunnel now, which the scheduler otherwise
 * does every TunnelRegistry::PURGE_FREQUENCY.
 */
class PurgeCommand extends AbstractBundleCommand
{
    protected static $defaultDescription = 'Drops the expired sessions of every tunnel';

    public function __construct(
        BundleService $bundleService,
        private readonly TunnelRegistry $tunnelRegistry,
        ?string $name = null,
    ) {
        parent::__construct($bundleService, $name);
    }

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyTunnelsBundle::class;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $output->writeln(
            sprintf('%d expired session(s) dropped.', $this->tunnelRegistry->purgeExpiredSessions())
        );

        return self::SUCCESS;
    }
}
