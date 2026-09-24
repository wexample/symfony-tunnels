<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Entity\TunnelSession;

/**
 * The leaf, and the one step able to send the visitor out of the tunnel.
 */
class StepFour extends AbstractTestStep
{
    public const string STEP_NAME = 'step-four';

    public const string QUERY_STRING_REDIRECTS = 'step-four-redirects';

    /**
     * The sessions this step was asked to clean up after, so that a test can
     * check the purge really reaches the steps.
     *
     * @var TunnelSession[]
     */
    public array $destroyedSessions = [];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function needsRedirect(TunnelCursor $cursor): null|RedirectResponse|TunnelCursor
    {
        if ($this->requestStack->getCurrentRequest()?->get(self::QUERY_STRING_REDIRECTS)) {
            return new RedirectResponse('/');
        }

        return $cursor->getFirstIncompletePreviousCursor();
    }

    public function onSessionDestroy(TunnelSession $session): void
    {
        $this->destroyedSessions[] = $session;
    }
}
