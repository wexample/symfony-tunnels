<?php

namespace Wexample\SymfonyTunnels\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;
use Wexample\SymfonyTunnels\Helper\TunnelTreeHelper;
use Wexample\SymfonyTunnels\Repository\TunnelSessionRepository;

/**
 * What the develop toolbar shows about the tunnels this browser is walking.
 *
 * The toolbar loads its panels in a request of their own, which knows nothing
 * of the page: the sessions are found the way a tunnel request finds them, from
 * the ids the browser session keeps.
 */
class TunnelDevelopService
{
    public const string MARK_NONE = 'none';

    public const string MARK_CURSOR = 'cursor';

    public const string MARK_COMPLETE = 'complete';

    public const string MARK_CURRENT = 'current';

    public function __construct(
        private readonly TunnelRegistry $tunnelRegistry,
        private readonly TunnelSessionRepository $tunnelSessionRepository,
        private readonly TunnelTracingService $tunnelTracingService,
    ) {
    }

    /**
     * @return array<array{
     *     tunnel: string,
     *     hash: string,
     *     status: string,
     *     created: string,
     *     current: ?string,
     *     pathsCount: int,
     *     sections: array<array{label: string, marks: string[]}>,
     *     variables: array
     * }>
     */
    public function buildBrowserSessionsData(SessionInterface $browserSession): array
    {
        $data = [];

        foreach ($this->tunnelRegistry->getTunnels() as $name => $tunnel) {
            $sessionId = $browserSession->get(AbstractTunnelController::BROWSER_SESSION_KEY_PREFIX . $name);
            $session = $sessionId ? $this->tunnelSessionRepository->find($sessionId) : null;

            if (! $session) {
                continue;
            }

            $entrypoint = $tunnel->createEntrypoint();
            $tunnel->setSession($session);

            $current = $session->getLastAccessedCursorHash()
                ? $tunnel->getCursor($session->getLastAccessedCursorHash())
                : null;
            $tunnel->setCurrentCursor($current);

            $paths = TunnelTreeHelper::buildPaths($entrypoint);
            $sections = [];

            foreach (TunnelTreeHelper::buildSections($entrypoint) as $section) {
                $marks = [];

                foreach (array_keys($paths) as $pathIndex) {
                    $cursor = $section->pathCursors[$pathIndex] ?? null;

                    $marks[] = match (true) {
                        $cursor === null => self::MARK_NONE,
                        $cursor === $current => self::MARK_CURRENT,
                        $cursor->isComplete() => self::MARK_COMPLETE,
                        default => self::MARK_CURSOR,
                    };
                }

                $sections[] = [
                    'label' => $section->cursor->step::getName()
                        . ($section->cursor->options ? ' ' . json_encode($section->cursor->options) : ''),
                    'marks' => $marks,
                ];
            }

            $data[] = [
                'tunnel' => $name,
                'hash' => $session->getHash(),
                'status' => $session->getStatus()->value,
                'created' => $session->getDateCreated()->format('Y-m-d H:i:s'),
                'current' => $current?->step::getName(),
                'pathsCount' => count($paths),
                'sections' => $sections,
                'variables' => $this->tunnelTracingService->buildVariableRows($tunnel),
            ];
        }

        return $data;
    }
}
