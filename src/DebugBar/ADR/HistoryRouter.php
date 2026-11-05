<?php

/**
 * This file is part of the Phalcon Framework.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\DebugBar\ADR;

use Phalcon\ADR\Router\RouterMatch;
use Phalcon\Contracts\ADR\ADRTypes;
use Phalcon\Contracts\ADR\Router\Router;
use Phalcon\Contracts\ADR\Router\RouterMatch as RouterMatchContract;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\Http\RequestInterface;

/**
 * Sends the history URL to `HistoryAction` and all other requests to the router
 * of the application. A listener cannot do this: `application:beforeHandle` cannot
 * stop `handle()`, and `application:afterHandle` runs after the routing. The
 * application router is `final`, so this class decorates it.
 *
 * @phpstan-import-type adr_middleware_map from ADRTypes
 */
final class HistoryRouter implements Router
{
    public function __construct(
        private readonly Router $router,
        private readonly HistoryEndpoint $endpoint
    ) {
    }

    /**
     * @return list<string>
     */
    public function candidatesFor(string $method, string $path): array
    {
        return $this->router->candidatesFor($method, $path);
    }

    public function classFor(string $method, string $path): string
    {
        return $this->router->classFor($method, $path);
    }

    public function match(RequestInterface $request): ?RouterMatchContract
    {
        if ($this->endpoint->matches()) {
            return new RouterMatch(HistoryAction::class);
        }

        return $this->router->match($request);
    }

    public function methodFor(string $className): ?string
    {
        return $this->router->methodFor($className);
    }

    public function pathFor(string $className): ?string
    {
        return $this->router->pathFor($className);
    }

    public function setActionDirectory(string $actionDirectory): Router
    {
        $this->router->setActionDirectory($actionDirectory);

        return $this;
    }

    public function setBaseNamespace(string $baseNamespace): Router
    {
        $this->router->setBaseNamespace($baseNamespace);

        return $this;
    }

    /**
     * @phpstan-param adr_middleware_map $middlewareMap
     */
    public function setMiddlewareMap(array $middlewareMap): Router
    {
        $this->router->setMiddlewareMap($middlewareMap);

        return $this;
    }

    public function setWordSeparator(string $wordSeparator): Router
    {
        $this->router->setWordSeparator($wordSeparator);

        return $this;
    }
}
