<?php

namespace Wexample\SymfonyTunnels\Service\Step;

use App\Wex\BaseBundle\Translation\Translator;
use JetBrains\PhpStorm\Pure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;

abstract class TunnelStep
{
    public const POSITION_TYPE_PREVIOUS = 'previous';

    public const POSITION_TYPE_NEXT = 'next';

    public const POSITION_TYPE_CURRENT = 'current';

    public const VARIABLE_COMPLETED = 'completed';

    public static string $name;

    private ?int $position = null;

    private AbstractTunnelManagerService $manager;

    public function init(): void
    {
        // To override...
    }

    public function getTranslationTitle(): string
    {
        return $this->trans('page_title');
    }

    public function trans(string $key): string
    {
        return $this->getTranslationDomain()
            .Translator::DOMAIN_SEPARATOR.
            $key;
    }

    public function getTranslationDomain(): string
    {
        return implode(
            Translator::DOMAIN_PART_SEPARATOR,
            [
                'tunnels',
                $this->getManager()->getTunnelName(),
                $this::$name,
            ]
        );
    }

    public function getManager(): AbstractTunnelManagerService
    {
        return $this->manager;
    }

    public function setManager(
        AbstractTunnelManagerService $manager
    ): void {
        $this->manager = $manager;
    }

    public function handleRequest(Request $request): self|Response|null
    {
        return $this;
    }

    public function getView(): string
    {
        return 'tunnels/'.$this->getManager()->getTunnelName().'/'.$this::$name.'.html.twig';
    }

    public function getViewParams(): array
    {
        return [
            'tunnel' => $this->getManager(),
        ];
    }

    /**
     * Return int position if redirection is expected,
     * else let null allow access to current step.
     */
    public function redirectToStepPosition(): ?int
    {
        return null;
    }

    public function setCompleted(bool $bool = true): void
    {
        $manager = $this
            ->getManager();

        $completed = $manager
            ->getTunnelSessionVariable(TunnelStep::VARIABLE_COMPLETED, (object) []);

        $key = $this->buildSessionKey();
        $completed->$key = $bool;

        $manager->setTunnelSessionVariable(
            TunnelStep::VARIABLE_COMPLETED,
            $completed
        );
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function preventAccess(): bool
    {
        // By default, allow access depending on direct access policy.
        return !$this->allowDirectAccess();
    }

    #[Pure]
    public function allowDirectAccess(): bool
    {
        // By default, direct access is
        // allowed only on the first step.
        if ($this->isFirst()) {
            return true;
        }

        if ($previousStep = $this->getManager()->getStepByPositionOffset(-1)) {
            return $previousStep->isCompleted();
        }

        return false;
    }

    #[Pure]
    public function isFirst(): bool
    {
        return 0 === $this->getPosition();
    }

    public function isCompleted(): bool
    {
        $completed = $this
            ->getManager()
            ->getTunnelSessionVariable(TunnelStep::VARIABLE_COMPLETED, (object) []);

        $key = $this->buildSessionKey();

        return isset($completed->$key) &&
            true === $completed->$key;
    }

    protected function buildSessionKey(): string
    {
        return 'step-'.$this->getPosition();
    }

    public function isLast(): bool
    {
        return $this->getPosition() === count(
                $this->getManager()->getTunnelSteps()
            ) - 1;
    }

    public function initAsCurrentStep(): void
    {
        // To override...
    }
}
