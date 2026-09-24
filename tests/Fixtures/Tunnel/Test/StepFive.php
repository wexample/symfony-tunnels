<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test;

use Wexample\SymfonyTunnels\Enum\TunnelStepCompleteStrategy;

/**
 * Reached only through the branch step three bis selects by hand, and only ever
 * with an option telling which variant it stands for. Nothing but the step
 * itself ever marks it done.
 */
class StepFive extends AbstractTestStep
{
    public const string STEP_NAME = 'step-five';

    public const string OPTION_NAME_VARIANT_ID = 'variant-id';

    public function completeStrategy(): TunnelStepCompleteStrategy
    {
        return TunnelStepCompleteStrategy::MANUAL;
    }
}
