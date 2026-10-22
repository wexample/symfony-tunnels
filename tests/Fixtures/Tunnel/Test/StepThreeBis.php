<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test;

use Wexample\SymfonyTunnels\Class\TunnelCursor;

/**
 * Appears three times under step two, once per option type. It writes a
 * variable global to the session and clears it when its branch is abandoned.
 */
class StepThreeBis extends AbstractTestStep
{
    public const string STEP_NAME = 'step-three-bis';

    public const string OPTION_NAME_TYPE = 'step-three-test-type';

    public const string VARIABLE_NAME_GLOBAL = 'global-three-bis-var';

    public const int VARIANT_ID_FIRST = 1;

    public function __construct(
        private readonly StepFour $stepFour,
        private readonly StepFive $stepFive,
    ) {
    }

    public function initAsCurrentStep(TunnelCursor $cursor): void
    {
        parent::initAsCurrentStep($cursor);

        $cursor->manager->setVariableValue(self::VARIABLE_NAME_GLOBAL, true);
    }

    public function onPreviousStepLoading(
        TunnelCursor $cursor,
        TunnelCursor $loadedPreviousCursor,
        int $distance,
    ): void {
        $cursor->manager->removeVariable(self::VARIABLE_NAME_GLOBAL);

        parent::onPreviousStepLoading($cursor, $loadedPreviousCursor, $distance);
    }

    public function selectNextCursor(TunnelCursor $cursor): ?TunnelCursor
    {
        return $cursor->findFirstNextByStep(StepFive::class);
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [
            $this->stepFour,
            [
                'step' => $this->stepFive,
                'options' => [StepFive::OPTION_NAME_VARIANT_ID => self::VARIANT_ID_FIRST],
            ],
        ];
    }
}
