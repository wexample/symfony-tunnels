<?php

namespace Wexample\SymfonyTunnels\Helper;

use ReflectionClass;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\Helpers\Helper\TextHelper;
use Wexample\SymfonyTunnels\Attribute\TunnelRoute;
use Wexample\SymfonyTunnels\Controller\AbstractTunnelController;

/**
 * Where a tunnel route lives and what it is called, for the loader declaring it
 * and the routing service pointing at it.
 *
 * A tunnel controller carrying a class-level #[Route] is mounted inside the
 * pages it belongs to: its routes take that path and that name prefix, like
 * any page of the controller, so URLs, route names, menus and breadcrumbs all
 * read the same tree. Without one, the route stands apart, under `/tunnel/`.
 */
final class TunnelRouteHelper
{
    public const string PATH_PREFIX = 'tunnel';

    /**
     * @var array<class-string, Route|false>
     */
    private static array $classRoutes = [];

    /**
     * @param class-string<AbstractTunnelController> $controllerClass
     */
    public static function buildRouteName(
        string $controllerClass,
        string $name = TunnelRoute::NAME_INDEX,
    ): string {
        $classRoute = self::findClassRoute($controllerClass);

        if ($classRoute?->name) {
            return $classRoute->name . $name;
        }

        return 'tunnel_' . TextHelper::toSnake($controllerClass::getTunnelManagerClass()::getName()) . '_' . $name;
    }

    /**
     * @param class-string<AbstractTunnelController> $controllerClass
     */
    public static function buildPath(
        string $controllerClass,
        TunnelRoute $tunnelRoute,
    ): string {
        $classRoute = self::findClassRoute($controllerClass);

        // A localized class route holds one path per locale: a tunnel takes the
        // first, not having a locale of its own.
        $classPath = is_array($classRoute?->path) ? (reset($classRoute->path) ?: null) : $classRoute?->path;

        $base = $classPath !== null
            ? [trim($classPath, '/')]
            : [
                self::PATH_PREFIX,
                TextHelper::toKebab(
                    TextHelper::removeSuffix(ClassHelper::getShortName($controllerClass), 'Controller')
                ),
            ];

        $parts = [
            ...$base,
            $tunnelRoute->name === TunnelRoute::NAME_INDEX ? null : $tunnelRoute->name,
            $tunnelRoute->pathPrefix,
            $tunnelRoute->cursorPlaceholder,
            $tunnelRoute->pathSuffix,
        ];

        return '/' . implode('/', array_filter($parts));
    }

    /**
     * The fixed start of every tunnel route path, up to the step, for what has
     * to name them all at once: the paths crawlers are kept out of.
     *
     * @param array<class-string<AbstractTunnelController>> $controllerClasses
     *
     * @return string[]
     */
    public static function buildPathPrefixes(array $controllerClasses): array
    {
        $prefixes = [];

        foreach ($controllerClasses as $controllerClass) {
            foreach ((new ReflectionClass($controllerClass))->getMethods() as $method) {
                foreach ($method->getAttributes(TunnelRoute::class) as $attribute) {
                    $path = self::buildPath($controllerClass, $attribute->newInstance());
                    $prefixes[] = rtrim(strstr($path, '{', true) ?: $path, '/') . '/';
                }
            }
        }

        return array_values(array_unique($prefixes));
    }

    /**
     * @param class-string $controllerClass
     */
    private static function findClassRoute(string $controllerClass): ?Route
    {
        if (! array_key_exists($controllerClass, self::$classRoutes)) {
            $attribute = (new ReflectionClass($controllerClass))->getAttributes(Route::class)[0] ?? null;
            self::$classRoutes[$controllerClass] = $attribute ? $attribute->newInstance() : false;
        }

        return self::$classRoutes[$controllerClass] ?: null;
    }
}
