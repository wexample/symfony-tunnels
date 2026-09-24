<?php

namespace Wexample\SymfonyTunnels\Enum;

/**
 * Where a cursor sits relative to another one, which the side list turns into
 * a CSS class.
 */
enum TunnelCursorPosition: string
{
    case SAME = 'same';

    case PREVIOUS = 'previous';

    case NEXT = 'next';

    case UNRELATED = 'unrelated';
}
