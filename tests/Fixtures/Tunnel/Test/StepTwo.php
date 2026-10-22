<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Enum\TunnelStepCompleteStrategy;

/**
 * Completes only once the next step is displayed, forbids direct access, and
 * opens the three named variants of step three bis.
 */
class StepTwo extends AbstractTestStep
{
    public const string STEP_NAME = 'step-two';

    public const string QUERY_STRING_COMPLETE = 'step-two-complete';

    public const string VARIABLE_NAME = 'step-two-test-var';

    private const array STEP_THREE_BIS_SESSION_OPTIONS = [
        'step-three-test-type' => 'session',
        'name' => 'typed-session',
    ];

    public function __construct(
        private readonly StepThree $stepThree,
        private readonly StepThreeBis $stepThreeBis,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function completeStrategy(): TunnelStepCompleteStrategy
    {
        return TunnelStepCompleteStrategy::ON_NEXT_INIT;
    }

    public function needsRedirect(TunnelCursor $cursor): null|RedirectResponse|TunnelCursor
    {
        if ($this->requestStack->getCurrentRequest()?->query->get(self::QUERY_STRING_COMPLETE)) {
            $redirectsTo = $cursor->findFirstNextByOptions(self::STEP_THREE_BIS_SESSION_OPTIONS);
            $cursor->setComplete($redirectsTo);

            return $redirectsTo;
        }

        if ($incompleteCursor = $cursor->getFirstIncompletePreviousCursor()) {
            return $incompleteCursor;
        }

        return parent::needsRedirect($cursor);
    }

    public function initAsCurrentStep(TunnelCursor $cursor): void
    {
        parent::initAsCurrentStep($cursor);

        $cursor->setVariableValue(self::VARIABLE_NAME, true);
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [
            $this->stepThree,
            [
                // The first variant of a step is the default one.
                'step' => $this->stepThreeBis,
                'name' => 'typed-default',
                'options' => ['step-three-test-type' => 'default'],
            ],
            [
                'step' => $this->stepThreeBis,
                'name' => 'typed-query-string',
                'options' => ['step-three-test-type' => 'queryString'],
            ],
            [
                'step' => $this->stepThreeBis,
                'name' => 'typed-session',
                'options' => self::STEP_THREE_BIS_SESSION_OPTIONS,
            ],
        ];
    }
}
