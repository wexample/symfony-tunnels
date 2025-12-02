<?php

namespace Wexample\SymfonyTunnels\Service;

use App\Controller\Tunnels\PaymentTunnelController;
use App\Entity\TunnelSession;
use App\Entity\User;
use App\Repository\TunnelSessionRepository;
use App\Service\EntityCrud\TunnelSessionCrudService;
use App\Wex\BaseBundle\Controller\AbstractController;
use App\Wex\BaseBundle\Service\AdaptiveResponse;
use DateTime;
use JetBrains\PhpStorm\Pure;
use stdClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Wexample\SymfonyHelpers\Helper\VariableHelper;
use Wexample\SymfonyTunnels\Service\Step\TunnelStep;

abstract class AbstractTunnelManagerService
{
    protected ?TunnelStep $currentStep = null;

    protected ?TunnelSession $tunnelSession = null;

    protected readonly Request $currentRequest;

    protected readonly RouterInterface $router;

    private array $tunnelSteps = [];

    private string $controllerClassName;

    public function __construct(
        protected AdaptiveResponse $adaptiveResponse,
        protected \Symfony\Bundle\SecurityBundle\Security $security,
        protected TunnelSessionCrudService $tunnelSessionCrudService,
        protected UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handleRequest(
        string $controllerClassName,
        Request $request,
        ?string $stepName
    ): Response|TunnelStep|null {
        $this->setControllerClassName($controllerClassName);

        $this->setCurrentRequest($request);

        // Redirect to first step.
        if (! $stepName) {
            $step = $this->getStepByPosition(0);

            return new RedirectResponse(
                $this->urlGenerator->generate(
                    $this->buildRouteName($step),
                    $this->buildRouteParams($step)
                )
            );
        }

        $step = $this->getStepByName($stepName);

        if (! $step) {
            return null;
        }

        $this->initSession(
            $request,
            $step
        );

        $this->setCurrentStep(
            $step
        );

        if ($response = $this->preventAccess($step)) {
            return $response;
        }

        $this->currentStep->initAsCurrentStep();

        // Step may be null if any match with url.
        if ($step->isFirst()) {
            // Reset completed variable.
            $this->setTunnelSessionVariable(
                TunnelStep::VARIABLE_COMPLETED,
                (object) []
            );
        }

        return $step->handleRequest($request);
    }

    protected function getStepByPosition(int $position): ?TunnelStep
    {
        return $this->getTunnelStepsList()[$position] ?? null;
    }

    public function getTunnelStepsList(): array
    {
        return array_values(
            $this->tunnelSteps
        );
    }

    protected function buildRouteName(TunnelStep $step): string
    {
        return $this
            ->getControllerClassName()::buildRouteName(
                PaymentTunnelController::ROUTE_INDEX
            );
    }

    public function getControllerClassName(): AbstractController|string
    {
        return $this->controllerClassName;
    }

    public function setControllerClassName(string $controllerClassName): void
    {
        $this->controllerClassName = $controllerClassName;
    }

    protected function buildRouteParams(TunnelStep $step): array
    {
        return [
            'step' => $step::$name,
        ];
    }

    protected function getStepByName(string $stepName): ?TunnelStep
    {
        $steps = $this->getTunnelSteps();

        foreach ($steps as $step) {
            if ($step::$name === $stepName) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return TunnelStep[]
     */
    public function getTunnelSteps(): array
    {
        return $this->tunnelSteps;
    }

    /**
     * @param TunnelStep[] $steps
     */
    protected function setTunnelSteps(array $steps): void
    {
        $this->tunnelSteps = [];
        $position = 0;

        foreach ($steps as $step) {
            $this->tunnelSteps[$step::class] = $step;
            $step->setPosition($position++);
            $step->setManager($this);
            $step->init();
        }
    }

    private function initSession(
        Request $request,
        TunnelStep $step
    ): void {
        // Search for tunnel session.
        $sessionId = $this->getBrowserSessionVariable('tunnel-session-id');
        /** @var User $user */
        $user = $this->security->getUser();

        if (! $sessionId) {
            $sessionId = (int) $request->get('tunnel');
        }

        if ($sessionId) {
            /** @var TunnelSessionRepository $sessionRepo */
            $sessionRepo = $this->tunnelSessionCrudService->getEntityRepository();
            $session = $sessionRepo->find($sessionId);

            // Assign only if not terminated.
            if ($session
                // Tunnel is not terminated.
                && ! $session->hasStatus(TunnelSession::STATUS_COMPLETED)
                // Date has not expired.
                && $session->getDateCreated() > (new DateTime())->modify('-1 day')
                // Browser IP does not change.
                && $session->getIpV4() === $request->getClientIp()
                // No user or user is the same as current.
                && (
                    is_null($session->getUser())
                    || (
                        $session->getUser()->getId() === $user->getId()
                    )
                )
            ) {
                $this->tunnelSession = $session;
            }
        }

        if (! $this->tunnelSession) {
            $this->tunnelSession = $this
                ->tunnelSessionCrudService
                ->createAndSaveTunnelSession(
                    $user
                );

            $this->setBrowserSessionVariable(
                'tunnel-session-id',
                $this->tunnelSession->getId()
            );
        }

        $this->tunnelSession->setLastAccessedStep(
            $step->getPosition()
        );

        $this
            ->tunnelSessionCrudService
            ->saveTunnelSession($this->tunnelSession);
    }

    public function getBrowserSessionVariable(
        string $name,
        $default = null
    ): mixed {
        $data = $this
            ->getCurrentRequest()
            ->getSession()
            ->get(
                $this->getTunnelSessionKey()
            );

        return $data[$name] ?? $default;
    }

    protected function getCurrentRequest(): Request
    {
        return $this->currentRequest;
    }

    protected function setCurrentRequest(Request $currentRequest): void
    {
        $this->currentRequest = $currentRequest;
    }

    protected function getTunnelSessionKey(): string
    {
        return 'tunnel-'.$this->getTunnelName();
    }

    abstract public static function getTunnelName(): string;

    public function setBrowserSessionVariable(
        string $name,
        mixed $value
    ): void {
        $sessionKey = $this->getTunnelSessionKey();
        $session = $this->getCurrentRequest()->getSession();

        // Get all data.
        $allStepsData =
            $session->get($sessionKey);

        // Append data.
        $allStepsData[$name] =
            $value;

        // Save.
        $session->set($sessionKey, $allStepsData);
    }

    /**
     * Check that all of previous steps allows to access to the current one.
     */
    protected function preventAccess(TunnelStep $step): ?RedirectResponse
    {
        $currentPosition = $step->getPosition();
        $newPosition = $step->redirectToStepPosition();

        // Current step do not allow access now.
        // Current step does not ask redirection.
        if (is_null($newPosition) && $step->preventAccess()) {
            $steps = $this->getTunnelSteps();

            foreach ($steps as $step) {
                $stepPosition = $step->getPosition();

                // This is a previous step and is not complete.
                if ($stepPosition < $currentPosition &&
                    ! $step->isCompleted()) {
                    $newPosition = $stepPosition;
                }
            }

            // No fallback step found, go back tunnel start.
            $newPosition = $newPosition ?: 0;
        }

        if (null !== $newPosition) {
            return new RedirectResponse(
                $this->buildStepUrl(
                    $this->getStepByPosition($newPosition)
                )
            );
        }

        return null;
    }

    public function buildStepUrl(
        TunnelStep $step
    ): string {
        return $this->urlGenerator->generate(
            $this->buildRouteName($step),
            $this->buildRouteParams($step),
        );
    }

    public function setTunnelSessionVariable(
        string $name,
        mixed $value = null
    ) {
        $data = $this->tunnelSession->getData() ?? new stdClass();

        $data->$name = $value;

        $this->tunnelSession->setData($data);

        $this->tunnelSessionCrudService->saveTunnelSession(
            $this->tunnelSession
        );
    }

    public function getTunnelSessionVariable(
        string $name,
        mixed $default = null
    ): mixed {
        $data = $this->tunnelSession->getData() ?? [];

        return $data->$name ?? $default;
    }

    public function clearUserSessionVariables()
    {
        return $this
            ->getCurrentRequest()
            ->getSession()
            ->remove($this->getTunnelSessionKey());
    }

    #[Pure]
    public function getStepsPositionType(int $stepNumber): string
    {
        $currentNumber = $this->getCurrentStep()->getPosition();

        if ($stepNumber > $currentNumber) {
            return TunnelStep::POSITION_TYPE_NEXT;
        }

        if ($stepNumber < $currentNumber) {
            return TunnelStep::POSITION_TYPE_PREVIOUS;
        }

        return TunnelStep::POSITION_TYPE_CURRENT;
    }

    public function getCurrentStep(): ?TunnelStep
    {
        return $this->currentStep;
    }

    public function setCurrentStep(
        TunnelStep $step
    ): void {
        $this->currentStep = $step;
    }

    public function adaptiveRedirectToNext(bool $saveComplete = true): void
    {
        // Mark current as complete.
        if ($saveComplete) {
            $this
                ->getCurrentStep()
                ->setCompleted();
        }

        if (! $this->getCurrentStep()->isLast()) {
            $this->adaptiveResponse->setRedirect(
                new RedirectResponse(
                    $this->getStepUrlForOffset(1)
                )
            );
        }
    }

    public function getStepUrlForOffset(int $offset): string
    {
        return $this->buildStepUrl(
            $this->getStepByPositionOffset($offset)
        );
    }

    public function getStepByPositionOffset(int $offset): ?TunnelStep
    {
        return $this->getStepByPosition(
            $this->getCurrentStep()->getPosition() + $offset
        );
    }

    public function redirectToNext(bool $saveComplete = true): ?AdaptiveResponse
    {
        // Mark current as complete.
        if ($saveComplete) {
            $this
                ->getCurrentStep()
                ->setCompleted();
        }

        if (! $this->getCurrentStep()->isLast()) {
            return $this->redirectToOffset(1);
        }

        return null;
    }

    public function redirectToOffset(int $offset): ?AdaptiveResponse
    {
        return $this->redirectToStep(
            $this->getStepByPositionOffset($offset)
        );
    }

    public function redirectToStep(TunnelStep $tunnelStep): ?AdaptiveResponse
    {
        return $this->adaptiveResponse->setRedirect(
            new RedirectResponse($this->buildStepUrl($tunnelStep))
        );
    }

    protected function findStepByRequestUrl(Request $request): ?TunnelStep
    {
        $steps = $this->getTunnelSteps();

        foreach ($steps as $step) {
            // Get converted version of route.
            $url = $this->buildStepUrl($step);

            if (parse_url($url)[VariableHelper::PATH] === $request->getPathInfo()) {
                return $step;
            }
        }

        return null;
    }

    protected function getRouter(): RouterInterface
    {
        return $this->router;
    }
}
