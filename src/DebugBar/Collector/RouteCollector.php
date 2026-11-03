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

namespace Phalcon\DebugBar\Collector;

use Closure;
use Phalcon\DebugBar\Contracts\Subscriber;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Mvc\RouterInterface;

/**
 * Reports the matched route. The router is the source of `router:matchedRoute`,
 * so it is captured there and read at `collect()` - by response time it has been
 * fully resolved (module/controller/action/params), which is not yet true when
 * the event fires. Nothing is resolved from the container. `setRoute()` gives the
 * panel values with no events manager.
 *
 * @phpstan-import-type grid_envelope from DebugBarTypes
 * @phpstan-import-type grid_panel from DebugBarTypes
 */
final class RouteCollector extends AbstractCollector implements Subscriber
{
    use EncodesJson;

    public const NAME = 'route';

    protected string $icon = 'icon-route';

    protected string $label = 'Route';

    protected string $panel = 'grid';

    /**
     * @var (Closure(): grid_panel)|grid_panel
     */
    private array | Closure $route = [];

    /**
     * @return grid_envelope
     */
    public function collect(): array
    {
        return [
            'panel' => $this->route instanceof Closure ? ($this->route)() : $this->route,
            'badge' => null,
        ];
    }

    /**
     * Takes the panel values, or a closure that returns them. The collector calls
     * the closure at `collect()`.
     *
     * @param (Closure(): grid_panel)|grid_panel $route
     */
    public function setRoute(array | Closure $route): void
    {
        $this->route = $route;
    }

    public function subscribe(ManagerInterface $eventsManager): void
    {
        $eventsManager->attach(
            'router:matchedRoute',
            function (EventInterface $event, mixed $router): void {
                if ($router instanceof RouterInterface) {
                    $this->setRoute(
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
