<?php

namespace Wexample\SymfonyTunnels\Controller;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyForms\Service\FormProcessor\FormResponsePayloadBuilder;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyLoader\Controller\AbstractPagesController;
use Wexample\SymfonyLoader\Helper\AdaptiveRequestHelper;
use Wexample\SymfonyLoader\Service\AdaptiveRendererService;
use Wexample\SymfonyLoader\Service\PageService;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Enum\TunnelStepCompleteStrategy;
use Wexample\SymfonyTunnels\Exception\TunnelInitVariableException;
use Wexample\SymfonyTunnels\Service\AbstractTunnelManagerService;
use Wexample\SymfonyTunnels\Service\Step\AbstractFormTunnelStep;
use Wexample\SymfonyTunnels\Service\TunnelRegistry;
use Wexample\SymfonyTunnels\Service\TunnelRoutingService;
use Wexample\SymfonyTunnels\Service\TunnelSessionService;

/**
 * The HTTP side of one tunnel. Its actions carry #[TunnelRoute], and the tunnel
 * they serve is named here, once, rather than repeated in every route.
 */
abstract class AbstractTunnelController extends AbstractPagesController
{
    public const string TAG_CONTROLLER = 'wexample.symfony_tunnels.controller';

    /**
     * Where the browser session keeps, per tunnel, the id of the tunnel session
     * being walked: the only tunnel state the PHP session ever holds.
     */
    public const string BROWSER_SESSION_KEY_PREFIX = 'tunnel-';

    /**
     * The layout base the loader reads from the query string. Carried over on
     * every redirect, so a tunnel opened in a modal stays in it.
     */
    public const string QUERY_STRING_LAYOUT = '__layout';

    public function __construct(
        AdaptiveRendererService $adaptiveRendererService,
        PageService $pageService,
        protected readonly TunnelRegistry $tunnelRegistry,
        protected readonly TunnelSessionService $tunnelSessionService,
        protected readonly TunnelRoutingService $tunnelRoutingService,
        protected readonly FormResponsePayloadBuilder $formResponsePayloadBuilder,
    ) {
        parent::__construct($adaptiveRendererService, $pageService);
    }

    /**
     * @return class-string<AbstractTunnelManagerService>
     */
    abstract public static function getTunnelManagerClass(): string;

    /**
     * Find where the visitor stands in the tunnel, send them where they belong
     * if that is somewhere else, and render the step otherwise.
     *
     * @param array $initVariables the values the tunnel is opened with
     */
    protected function handleTunnelRequest(
        Request $request,
        array $initVariables = [],
    ): Response {
        $tunnel = $this->tunnelRegistry->getTunnel(static::getTunnelManagerClass());

        $tunnel->createEntrypoint();
        $this->tunnelSessionService->purgeExpiredSessions($tunnel);

        try {
            $tunnel->setInitialVariables($initVariables);
        } catch (TunnelInitVariableException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }

        $session = $this->tunnelSessionService->findOrCreateSession(
            $tunnel::getName(),
            $request->query->get(TunnelSessionService::QUERY_STRING_SESSION_HASH),
            $request->getSession()->get(self::BROWSER_SESSION_KEY_PREFIX . $tunnel::getName()),
            $this->getTunnelUserIdentifier(),
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
        );

        $tunnel->setSession($session);

        $cursor = $this->guessCursorFromRequest($tunnel, $request);

        if (! $cursor) {
            $this->rememberSession($tunnel, $request);

            return $this->redirectToEntrypoint($tunnel, $request);
        }

        $session = $this->tunnelSessionService->initCursorSession($session, $cursor);
        $tunnel->setSession($session);
        $this->rememberSession($tunnel, $request);

        if ($redirect = $tunnel->needsGlobalRedirect($cursor)) {
            if ($redirect instanceof RedirectResponse) {
                return $redirect;
            }

            $this->completeOnRedirect($cursor, $redirect);

            return $this->redirectToCursor($redirect, $request);
        }

        $tunnel->setCurrentCursor($cursor);
        $cursor->step->initAsCurrentStep($cursor);

        if ($cursor->step instanceof AbstractFormTunnelStep) {
            return $this->handleFormStep($cursor, $request);
        }

        return $this->renderTunnelStep($cursor);
    }

    protected function renderTunnelStep(
        TunnelCursor $cursor,
        array $parameters = [],
    ): Response {
        return $this->adaptiveRender(
            $this->buildTemplatePath($cursor->step->buildStepView($cursor)),
            $parameters + $this->buildTunnelStepViewParams($cursor)
        );
    }

    /**
     * Show the form of the step, or handle its submission. The form posts back
     * to the step URL; a valid one moves the visitor to wherever the step says,
     * inside the modal or panel the tunnel was opened in when there is one.
     */
    protected function handleFormStep(
        TunnelCursor $cursor,
        Request $request,
    ): Response {
        /** @var AbstractFormTunnelStep $step */
        $step = $cursor->step;
        $processor = $step->getFormProcessor($cursor);
        $data = $step->buildFormData($cursor);

        if (! $request->isMethod(Request::METHOD_POST)) {
            return $this->renderFormStep($cursor, $processor->createForm($data));
        }

        $form = $processor->handleSubmissionWithData($request, $data);
        $next = null;

        // The processor has the last word on validity, as it does when it
        // decides whether to call its own onValid().
        if ($form->isSubmitted() && $processor->formIsValid($form)) {
            $next = $step->onFormValid($form, $cursor);
            $this->setFormSuccessAction($processor, $next, $request);
        }

        if (RequestHelper::isJsonRequest($request)) {
            return new JsonResponse($this->formResponsePayloadBuilder->build($processor, $form));
        }

        if ($next) {
            return $this->redirectToCursor($next, $request);
        }

        return $this->renderFormStep($cursor, $form);
    }

    private function renderFormStep(
        TunnelCursor $cursor,
        FormInterface $form,
    ): Response {
        return $this->renderTunnelStep($cursor, ['form' => $form->createView()]);
    }

    private function setFormSuccessAction(
        AbstractFormProcessor $processor,
        ?TunnelCursor $next,
        Request $request,
    ): void {
        if (! $next) {
            $processor->setSuccessAction(['type' => AbstractFormProcessor::ACTION_EMBED_STAY]);

            return;
        }

        $url = $this->buildCursorRedirectUrl($next, $request);

        $processor->setSuccessAction([
            'type' => AdaptiveRequestHelper::isEmbedded($request)
                ? AbstractFormProcessor::ACTION_EMBED_REDIRECT
                : AbstractFormProcessor::ACTION_REDIRECT,
            'url' => $url,
        ]);
    }

    protected function buildTunnelStepViewParams(TunnelCursor $cursor): array
    {
        return [
                'tunnel' => $cursor->manager,
                'tunnelStep' => $cursor->step,
                'tunnelCursor' => $cursor,
            ]
            + $cursor->manager->buildViewParams($cursor)
            + $cursor->step->buildViewParams($cursor);
    }

    /**
     * Among the cursors whose route params the request carries, the one the
     * visitor means, negotiated with the query options and the path walked.
     */
    protected function guessCursorFromRequest(
        AbstractTunnelManagerService $tunnel,
        Request $request,
    ): ?TunnelCursor {
        $routeParams = $request->attributes->get('_route_params', []);
        $candidates = [];

        foreach ($tunnel->getCursors() as $cursor) {
            $cursorParams = $this->tunnelRoutingService->buildCursorRouteParams($cursor);

            if (array_intersect_assoc($cursorParams, $routeParams) === $cursorParams) {
                $candidates[] = $cursor;
            }
        }

        return $tunnel->selectCurrentCursor($candidates, $this->parseCursorOptions($request));
    }

    /**
     * The visitor's security identifier, when the application has security and
     * someone is logged in.
     */
    protected function getTunnelUserIdentifier(): ?string
    {
        if (! $this->container->has('security.token_storage')) {
            return null;
        }

        return $this->getUser()?->getUserIdentifier();
    }

    private function parseCursorOptions(Request $request): ?array
    {
        $options = $request->query->all()[TunnelCursor::QUERY_STRING_CURSOR_OPTIONS] ?? null;

        if (! is_array($options)) {
            return null;
        }

        return array_map(RequestHelper::parseRequestValue(...), $options);
    }

    /**
     * A step completing on redirect is done with as soon as it sends the
     * visitor to one of its own children.
     */
    private function completeOnRedirect(
        TunnelCursor $cursor,
        TunnelCursor $redirect,
    ): void {
        $strategy = $cursor->step->completeStrategy();

        $completes = match ($strategy) {
            TunnelStepCompleteStrategy::ON_NEXT_REDIRECT => $cursor->hasNext($redirect),
            TunnelStepCompleteStrategy::ON_NEXT_REDIRECT_RECURSIVE => $cursor->hasNext($redirect, true),
            default => false,
        };

        if ($completes) {
            $cursor->setComplete($redirect);
        }
    }

    private function rememberSession(
        AbstractTunnelManagerService $tunnel,
        Request $request,
    ): void {
        $request->getSession()->set(
            self::BROWSER_SESSION_KEY_PREFIX . $tunnel::getName(),
            (string) $tunnel->getSession()->getId()
        );
    }

    private function redirectToEntrypoint(
        AbstractTunnelManagerService $tunnel,
        Request $request,
    ): Response {
        // A step was named but matches nothing reachable: that is a wrong URL,
        // not an invitation to start over.
        if (isset($request->attributes->get('_route_params', [])['step'])) {
            throw $this->createNotFoundException('No cursor of tunnel ' . $tunnel::getName() . ' matches this request.');
        }

        return $this->redirectToCursor($tunnel->getEntrypointCursor(), $request);
    }

    private function redirectToCursor(
        TunnelCursor $cursor,
        Request $request,
    ): RedirectResponse {
        return $this->redirect($this->buildCursorRedirectUrl($cursor, $request));
    }

    private function buildCursorRedirectUrl(
        TunnelCursor $cursor,
        Request $request,
    ): string {
        $params = [];

        if ($layout = $request->query->get(self::QUERY_STRING_LAYOUT)) {
            $params[self::QUERY_STRING_LAYOUT] = $layout;
        }

        return $this->tunnelRoutingService->buildCursorUrl($cursor, $params);
    }
}
