<?php

namespace Wexample\SymfonyTunnels\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Every tunnel the application declares, found by their tag rather than listed
 * by hand: declaring a manager is enough to make it reachable by name.
 */
class TunnelRegistry
{
    public const string TAG_TUNNEL = 'wexample.symfony_tunnels.tunnel';

    /**
     * The shortest expiration a step should ask for: a session is dropped at
     * most this long after it expired.
     */
    public const string PURGE_FREQUENCY = '15 minutes';

    /**
     * @var array<string, AbstractTunnelManagerService>
     */
    private array $tunnels = [];

    /**
     * @param iterable<AbstractTunnelManagerService> $tunnels
     */
    public function __construct(
        #[AutowireIterator(self::TAG_TUNNEL)]
        iterable $tunnels,
        private readonly TunnelSessionService $sessionService,
    ) {
        foreach ($tunnels as $tunnel) {
            $this->tunnels[$tunnel::getName()] = $tunnel;
        }
    }

    /**
     * @param string $nameOrClass the tunnel name, or its manager class
     */
    public function getTunnel(string $nameOrClass): ?AbstractTunnelManagerService
    {
        if (is_subclass_of($nameOrClass, AbstractTunnelManagerService::class)) {
            $nameOrClass = $nameOrClass::getName();
        }

        return $this->tunnels[$nameOrClass] ?? null;
    }

    /**
     * @return array<string, AbstractTunnelManagerService>
     */
    public function getTunnels(): array
    {
        return $this->tunnels;
    }

    /**
     * Drop the expired sessions of every tunnel, each one letting its own steps
     * clean up after them.
     *
     * Run by the scheduler of the application, out of any visitor's request:
     * the task is declared in services.yaml.
     *
     * @return int the number of sessions dropped
     */
    public function purgeExpiredSessions(): int
    {
        $count = 0;

        foreach ($this->tunnels as $tunnel) {
            $tunnel->createEntrypoint();
            $count += $this->sessionService->purgeExpiredSessions($tunnel);
        }

        return $count;
    }
}
