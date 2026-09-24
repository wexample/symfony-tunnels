<?php

namespace Wexample\SymfonyTunnels\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyTunnels\Service\TunnelRegistry;
use Wexample\SymfonyTunnels\Service\TunnelTracingService;
use Wexample\SymfonyTunnels\WexampleSymfonyTunnelsBundle;

/**
 * Prints the map of a tunnel, one column per path, or with --tree the tree of
 * cursors it is built from.
 */
class InfoCommand extends AbstractBundleCommand
{
    protected static $defaultDescription = 'Prints the map of a tunnel';

    public function __construct(
        BundleService $bundleService,
        private readonly TunnelRegistry $tunnelRegistry,
        private readonly TunnelTracingService $tunnelTracingService,
        ?string $name = null,
    ) {
        parent::__construct($bundleService, $name);
    }

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyTunnelsBundle::class;
    }

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'The tunnel name, or its manager class'
            )
            ->addOption(
                'tree',
                null,
                InputOption::VALUE_NONE,
                'Print the tree of cursors instead of the map'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $name = $input->getArgument('name');
        $tunnel = $this->tunnelRegistry->getTunnel($name);

        if (!$tunnel) {
            $output->writeln(
                '<error>No tunnel named "' . $name . '". Known tunnels: '
                . implode(', ', array_keys($this->tunnelRegistry->getTunnels())) . '</error>'
            );

            return Command::FAILURE;
        }

        $output->writeln(
            $input->getOption('tree')
                ? $this->tunnelTracingService->traceTree($tunnel)
                : $this->tunnelTracingService->traceSections($tunnel)
        );

        return Command::SUCCESS;
    }
}
