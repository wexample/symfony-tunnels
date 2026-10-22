<?php

namespace Wexample\SymfonyTunnels\Enum;

enum TunnelSessionStatus: string
{
    case OPENED = 'opened';

    case COMPLETED = 'completed';

    case PENDING_ASYNC_ACTION = 'pending_async_action';
}
