<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test;

use Wexample\SymfonyTunnels\Class\TunnelCursor;

/**
 * The entrypoint: it opens the same step three times, with different options,
 * which is the whole point of a tree of cursors.
 */
class StepOne extends AbstractTestStep
{
    public const string STEP_NAME = 'step-one';

    public function __construct(
        private readonly StepTwo $stepTwo,
    ) {
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [
            $this->stepTwo,
            [
                'step' => $this->stepTwo,
                'options' => [
                    'lorem' => 'ipsum',
                    'name' => 'selected-if-step-three-direct-access',
                ],
            ],
            [
                'step' => $this->stepTwo,
                'options' => ['sit' => 'amet'],
            ],
        ];
    }
}
