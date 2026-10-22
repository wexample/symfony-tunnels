<?php

namespace Wexample\SymfonyTunnels\Service;

use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSessionVariable;
use Wexample\SymfonyTunnels\Enum\TunnelSessionStatus;
use Wexample\SymfonyTunnels\Repository\TunnelSessionVariableRepository;

/**
 * Takes up a flow left waiting on something outside the request — a payment
 * confirmed by a webhook, typically — with no visitor and no URL to go by.
 *
 * A step waiting that way sets its session to `pending_async_action` and keeps
 * the identifier the outside event will carry as a variable. The event handler,
 * which stays in the application, hands that identifier over here.
 */
class TunnelResumeService
{
    public function __construct(
        private readonly TunnelRegistry $tunnelRegistry,
        private readonly TunnelSessionService $tunnelSessionService,
        private readonly TunnelSessionVariableRepository $tunnelSessionVariableRepository,
    ) {
    }

    /**
     * Rebuild the tunnel of every pending session holding this variable, and
     * hand the cursor the variable was stored under to $onCursor.
     *
     * @param callable(?TunnelCursor, AbstractTunnelManagerService): void $onCursor
     *        receives a null cursor when the variable was global to the session
     *
     * @return int the number of sessions resumed
     */
    public function resumeByVariable(
        string $name,
        mixed $value,
        callable $onCursor,
    ): int {
        $resumed = 0;

        foreach ($this->findPendingVariables($name, $value) as $variable) {
            $session = $variable->getTunnelSession();
            $tunnel = $this->tunnelRegistry->getTunnel($session->getTunnel());

            $tunnel->createEntrypoint();
            $tunnel->setSession($session);
            $tunnel->autoInitVariables(
                $this->tunnelSessionService->findInitialVariableValues($session)
            );

            $cursor = $variable->isGlobal()
                ? null
                : $tunnel->getCursor($variable->getCursorHash());

            $onCursor($cursor, $tunnel);
            ++$resumed;
        }

        return $resumed;
    }

    /**
     * @return TunnelSessionVariable[]
     */
    private function findPendingVariables(
        string $name,
        mixed $value,
    ): array {
        // Values are JSON, which not every database compares the same way:
        // the name narrows the query, the value is compared here.
        return array_values(
            array_filter(
                $this->tunnelSessionVariableRepository->findBy(['name' => $name]),
                static fn (TunnelSessionVariable $variable): bool => $variable->getValue() === $value
                    && $variable->getTunnelSession()->hasStatus(TunnelSessionStatus::PENDING_ASYNC_ACTION)
            )
        );
    }
}
