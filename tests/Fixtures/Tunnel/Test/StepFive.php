<?php

namespace Wexample\SymfonyTunnels\Tests\Fixtures\Tunnel\Test;

use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Enum\TunnelStepCompleteStrategy;

/**
 * Reached only through the branch step three bis selects by hand, and only ever
 * with an option telling which variant it stands for. Nothing but the step
 * itself ever marks it done. It keeps a session for minutes only, the way a
 * step holding a stock for the visitor does.
 */
class StepFive extends AbstractTestStep
{
    public const string STEP_NAME = 'step-five';

    public const string OPTION_NAME_VARIANT_ID = 'variant-id';

    public const string SESSION_EXPIRATION = '15 minutes';

    public function completeStrategy(): TunnelStepCompleteStrategy
    {
        return TunnelStepCompleteStrategy::MANUAL;
    }

    public function getSessionExpiration(TunnelCursor $cursor): string
    {
        return self::SESSION_EXPIRATION;
    }
}
