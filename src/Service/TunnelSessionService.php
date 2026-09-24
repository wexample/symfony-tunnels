<?php

namespace Wexample\SymfonyTunnels\Service;

use DateTime;
use DateTimeInterface;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSession;
use Wexample\SymfonyTunnels\Interface\TunnelSessionStorageInterface;
use Wexample\SymfonyTunnels\Repository\TunnelSessionRepository;
use Wexample\SymfonyTunnels\Repository\TunnelSessionVariableRepository;

/**
 * The database side of a tunnel: finding the session a request belongs to,
 * starting a new one when it does not, and holding the variables of both.
 */
class TunnelSessionService implements TunnelSessionStorageInterface
{
    /**
     * How long an opened session survives without being walked.
     */
    public const string SESSION_EXPIRATION = '1 day';

    /**
     * The key the resume hash travels under, in the query string.
     */
    public const string QUERY_STRING_SESSION_HASH = 'tunnel';

    public function __construct(
        private readonly TunnelSessionRepository $tunnelSessionRepository,
        private readonly TunnelSessionVariableRepository $tunnelSessionVariableRepository,
    ) {
    }

    public static function buildExpirationDate(): DateTimeInterface
    {
        return (new DateTime())->modify('-' . self::SESSION_EXPIRATION);
    }

    /**
     * The session the request is about: the one its resume hash names, the one
     * the browser session remembers, or a new one.
     *
     * A hash alone never reopens someone else's flow: it has to name a session
     * of this very tunnel, belonging to the same visitor.
     */
    public function findOrCreateSession(
        string $tunnelName,
        ?string $resumeHash = null,
        ?string $browserSessionId = null,
        ?string $userIdentifier = null,
        ?string $ipV4 = null,
        ?string $browserData = null,
    ): TunnelSession {
        $session = $this->findResumableSession(
            $tunnelName,
            $resumeHash,
            $browserSessionId,
            $userIdentifier
        );

        if ($session) {
            return $session;
        }

        return $this->tunnelSessionRepository->saveNewTunnelSession(
            $tunnelName,
            $ipV4,
            $userIdentifier,
            $browserData
        );
    }

    /**
     * Some steps want to be entered on a clean session rather than continue the
     * one at hand, the completed flow being the usual case.
     */
    public function initCursorSession(
        TunnelSession $session,
        TunnelCursor $cursor,
    ): TunnelSession {
        if (!$cursor->step->tunnelSessionRecreate($session, $cursor)) {
            return $session;
        }

        return $this->tunnelSessionRepository->saveNewTunnelSession(
            $session->getTunnel(),
            $session->getIpV4(),
            $session->getUserIdentifier(),
            $session->getBrowserData()
        );
    }

    /**
     * Drop the sessions of this tunnel nobody came back to, letting every step
     * clean up what it had created for them.
     *
     * @return int the number of sessions dropped
     */
    public function purgeExpiredSessions(AbstractTunnelManagerService $manager): int
    {
        $expired = $this->tunnelSessionRepository->findExpired(self::buildExpirationDate());
        $expired = array_filter(
            $expired,
            static fn (TunnelSession $session): bool => $session->getTunnel() === $manager::getName()
        );

        foreach ($expired as $session) {
            foreach ($manager->getSteps() as $step) {
                $step->onSessionDestroy($session);
            }
        }

        $this->tunnelSessionRepository->removeAll($expired);

        return count($expired);
    }

    public function saveSession(TunnelSession $session): void
    {
        $this->tunnelSessionRepository->save($session);
    }

    public function getVariableValue(
        TunnelSession $session,
        string $name,
        ?string $cursorHash = null,
        mixed $default = null,
    ): mixed {
        $variable = $this->tunnelSessionVariableRepository->findOneByNameForCursor(
            $session,
            $name,
            $cursorHash
        );

        return $variable ? $variable->getValue() : $default;
    }

    public function setVariableValue(
        TunnelSession $session,
        string $name,
        mixed $value,
        ?string $cursorHash = null,
        bool $initial = false,
    ): void {
        $variable = $this->tunnelSessionVariableRepository->findOneByNameForCursor(
            $session,
            $name,
            $cursorHash
        );

        if ($variable) {
            $variable->setValue($value);
            $this->tunnelSessionVariableRepository->save($variable);

            return;
        }

        $this->tunnelSessionVariableRepository->saveNewTunnelSessionVariable(
            $session,
            $name,
            $value,
            $cursorHash,
            $initial
        );
    }

    public function removeVariable(
        TunnelSession $session,
        string $name,
        ?string $cursorHash = null,
    ): bool {
        $variable = $this->tunnelSessionVariableRepository->findOneByNameForCursor(
            $session,
            $name,
            $cursorHash
        );

        if (!$variable) {
            return false;
        }

        $session->removeTunnelSessionVariable($variable);
        $this->tunnelSessionVariableRepository->remove($variable);

        return true;
    }

    public function removeCursorVariables(
        TunnelSession $session,
        string $cursorHash,
    ): int {
        $variables = $this->tunnelSessionVariableRepository->findByCursorHash($session, $cursorHash);

        foreach ($variables as $variable) {
            $session->removeTunnelSessionVariable($variable);
        }

        $this->tunnelSessionVariableRepository->removeAll($variables);

        return count($variables);
    }

    /**
     * @return array<string, mixed> the values stored when the tunnel was opened
     */
    public function findInitialVariableValues(TunnelSession $session): array
    {
        $values = [];

        $variables = $this->tunnelSessionVariableRepository->findBy([
            'tunnelSession' => $session,
            'initial' => true,
        ]);

        foreach ($variables as $variable) {
            $values[$variable->getName()] = $variable->getValue();
        }

        return $values;
    }

    private function findResumableSession(
        string $tunnelName,
        ?string $resumeHash,
        ?string $browserSessionId,
        ?string $userIdentifier,
    ): ?TunnelSession {
        $expirationDate = self::buildExpirationDate();

        if ($resumeHash) {
            $session = $this->tunnelSessionRepository->findOneByHashForTunnel(
                $resumeHash,
                $tunnelName,
                $userIdentifier
            );

            if ($session && !$session->isExpired($expirationDate)) {
                return $session;
            }
        }

        if ($browserSessionId) {
            $session = $this->tunnelSessionRepository->find($browserSessionId);

            if ($session
                && $session->getTunnel() === $tunnelName
                && $session->getUserIdentifier() === $userIdentifier
                && !$session->isExpired($expirationDate)
            ) {
                return $session;
            }
        }

        return null;
    }
}
