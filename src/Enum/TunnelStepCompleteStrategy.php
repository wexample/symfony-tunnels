<?php

namespace Wexample\SymfonyTunnels\Enum;

/**
 * When a step gets its "complete" flag, which is what lets the visitor move on.
 */
enum TunnelStepCompleteStrategy: string
{
    /**
     * Complete as soon as the step is displayed. A step with a single following
     * cursor also remembers it as the branch that was taken.
     */
    case ON_INIT = 'on_init';

    /**
     * Complete once the next step is displayed, so the step stays incomplete
     * while it is the one being looked at.
     */
    case ON_NEXT_INIT = 'on_next_init';

    /**
     * Complete when the step redirects to one of its own children.
     */
    case ON_NEXT_REDIRECT = 'on_next_redirect';

    /**
     * Same, extended to any descendant of the step.
     */
    case ON_NEXT_REDIRECT_RECURSIVE = 'on_next_redirect_recursive';

    /**
     * Never completed by the engine: the step decides, usually on a valid form.
     */
    case MANUAL = 'manual';
}
