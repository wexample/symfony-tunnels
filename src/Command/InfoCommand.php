<?php

namespace Wexample\SymfonyTunnels\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Service\BundleService;
use Wexample\SymfonyTunnels\Repository\TunnelSessionRepository;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\TunnelRegistry;
use Wexample\SymfonyTunnels\Service\TunnelTracingService;
use Wexample\SymfonyTunnels\WexampleSymfonyTunnelsBundle;

/**
 * Prints the map of a tunnel, one column per path, or with --tree the tree of
 * cursors it is built from. With --session, the map shows where that session
 * stands, followed by what it holds.
 */
class InfoCommand extends AbstractBundleCommand
{
    protected static $defaultDescription = 'Prints the map of a tunnel';

    public function __construct(
        BundleService $bundleService,
        private readonly TunnelRegistry $tunnelRegistry,
        private readonly TunnelTracingService $tunnelTracingService,
        private readonly TunnelSessionRepository $tunnelSessionRepository,
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
            )
            ->addOption(
                'session',
                null,
                InputOption::VALUE_REQUIRED,
                'The resume hash of a session to show on the map'
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

        if ($hash = $input->getOption('session')) {
            return $this->traceSession($tunnel, $hash, $output);
        }

        $output->writeln(
            $input->getOption('tree')
                ? $this->tunnelTracingService->traceTree($tunnel)
                : $this->tunnelTracingService->traceSections($tunnel)
        );

        return Command::SUCCESS;
    }

    private function traceSession(
        AbstractTunnelManagerService $tunnel,
        string $hash,
        OutputInterface $output,
    ): int {
        $session = $this->tunnelSessionRepository->findOneBy([
            'hash' => $hash,
            'tunnel' => $tunnel::getName(),
        ]);

        if (!$session) {
            $output->writeln('<error>No session of tunnel "' . $tunnel::getName() . '" has the hash "' . $hash . '".</error>');

            return Command::FAILURE;
        }

        $tunnel->createEntrypoint();
        $tunnel->setSession($session);
        $tunnel->setCurrentCursor(
            $session->getLastAccessedCursorHash()
                ? $tunnel->getCursor($session->getLastAccessedCursorHash())
                : null
        );

        $output->writeln([
            'Session ' . $session->getHash() . ' — status ' . $session->getStatus()->value
                . ', created ' . $session->getDateCreated()->format('Y-m-d H:i:s')
                . ($session->getUserIdentifier() ? ' by ' . $session->getUserIdentifier() : ''),
            '<fg=blue>●</> current  <fg=yellow>●</> complete',
            '',
            $this->tunnelTracingService->traceSections($tunnel),
            '',
        ]);

        $rows = $this->tunnelTracingService->buildVariableRows($tunnel);

        (new Table($output))
            ->setHeaders(['Scope', 'Name', 'Value', 'Initial'])
            ->setRows(array_map(
                static fn (array $row): array => [$row['scope'], $row['name'], $row['value'], $row['initial'] ? 'yes' : ''],
                $rows
            ))
            ->render();

        return Command::SUCCESS;
    }
}
