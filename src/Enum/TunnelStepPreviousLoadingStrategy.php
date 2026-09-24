<?php

namespace Wexample\SymfonyTunnels\Enum;

/**
 * What happens to a branch when the visitor goes back before it.
 */
enum TunnelStepPreviousLoadingStrategy: string
{
    /**
     * Drop everything the abandoned branch had stored, so walking forward again
     * starts from a clean state.
     */
    case RESET = 'reset';

    case KEEP = 'keep';
}
