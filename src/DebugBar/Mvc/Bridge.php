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

namespace Phalcon\DebugBar\Mvc;

use Phalcon\DebugBar\Collector\EncodesJson;
use Phalcon\DebugBar\Collector\ExceptionsCollector;
use Phalcon\DebugBar\Collector\RouteCollector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Mvc\RouterInterface;
use Throwable;

/**
 * Feeds the collectors from the events of an MVC application. The router is the
 * source of the route event, and this class reads it at `collect()`: the router is
 * not fully resolved when the event fires. An exception is only recorded: it
 * continues to the application, and the bar does not handle it.
 */
final class Bridge
{
    use EncodesJson;

    public function __construct(private readonly DebugBar $bar)
    {
    }

    public function subscribe(ManagerInterface $eventsManager): void
    {
        $collectors = $this->bar->getCollectors();

        $exceptions = $collectors[ExceptionsCollector::NAME] ?? null;
        if ($exceptions instanceof ExceptionsCollector) {
            $this->subscribeExceptions($eventsManager, $exceptions);
        }

        $route = $collectors[RouteCollector::NAME] ?? null;
        if ($route instanceof RouteCollector) {
            $this->subscribeRoute($eventsManager, $route);
        }
    }

    private function subscribeExceptions(ManagerInterface $eventsManager, ExceptionsCollector $collector): void
    {
        $eventsManager->attach(
            'dispatch:beforeException',
            function (EventInterface $event, mixed $dispatcher, mixed $exception) use ($collector): void {
                if ($exception instanceof Throwable) {
                    $collector->addThrowable($exception);
                }
            }
        );
    }

    private function subscribeRoute(ManagerInterface $eventsManager, RouteCollector $collector): void
    {
        $eventsManager->attach(
            'router:matchedRoute',
            function (EventInterface $event, mixed $router) use ($collector): void {
                if ($router instanceof RouterInterface) {
                    $collector->setRoute(
                        fn (): array => [
                            'Module'     => $router->getModuleName(),
                            'Namespace'  => $router->getNamespaceName(),
                            'Controller' => $router->getControllerName(),
                            'Action'     => $router->getActionName(),
                            'Params'     => $this->jsonOrEmpty($router->getParams()),
                        ]
                    );
                }
            }
        );
    }
}
