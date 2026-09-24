<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test;

use Wexample\SymfonyTunnels\Class\TunnelCursor;

/**
 * Once done with, it forbids going back before it, the way a payment step does.
 */
class StepThree extends AbstractTestStep
{
    public const string STEP_NAME = 'step-three';

    public function __construct(
        private readonly StepFour $stepFour,
    ) {
    }

    public function allowAccessOf(
        TunnelCursor $cursor,
        TunnelCursor $siblingCursor,
    ): bool {
        return !$cursor->isComplete() || !$cursor->hasPreviousRecursive($siblingCursor);
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [
            $this->stepFour,
        ];
    }
}
