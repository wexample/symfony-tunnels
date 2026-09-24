<?php

namespace Wexample\SymfonyTunnels\Attribute;

use Attribute;

/**
 * Declares a tunnel entry point on a controller action. The path is built as
 * `tunnel/<controller>/<name>/<prefix>/<cursor>/<suffix>`, the name being left
 * out for `index`, and the route is named after the tunnel, not the controller.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class TunnelRoute
{
    public const string NAME_INDEX = 'index';

    public function __construct(
        public readonly string $name = self::NAME_INDEX,
        public readonly ?string $pathPrefix = null,
        public readonly ?string $pathSuffix = null,
        /**
         * Where the step shows in the path. By default it is optional: a URL
         * without it lands on the entrypoint.
         */
        public readonly string $cursorPlaceholder = '{step?}',
    ) {
    }
}
