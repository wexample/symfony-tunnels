<?php

namespace Wexample\SymfonyTunnels\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Wexample\SymfonyTunnels\Service\TunnelDevelopService;

class TunnelDevelopExtension extends AbstractExtension
{
    public function __construct(
        private readonly TunnelDevelopService $tunnelDevelopService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tunnel_develop_sessions', $this->tunnelDevelopSessions(...)),
        ];
    }

    public function tunnelDevelopSessions(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (! $request?->hasSession()) {
            return [];
        }

        return $this->tunnelDevelopService->buildBrowserSessionsData($request->getSession());
    }
}
