<?php

namespace Wexample\SymfonyTunnels\Enum;

/**
 * How far back a step looks for an incomplete ancestor before letting the
 * visitor in.
 */
enum TunnelStepRedirectStrategy: string
{
    case ANY_PREVIOUS_INCOMPLETE = 'any_previous_incomplete';

    case DIRECT_PREVIOUS_INCOMPLETE = 'direct_previous_incomplete';
}
