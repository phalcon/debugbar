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

use Phalcon\DebugBar\Contracts\Subscriber;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;
use Phalcon\Mvc\RouterInterface;

use function is_string;
use function json_encode;

/**
 * Reports the matched route. The router is the source of `router:matchedRoute`,
 * so it is captured there and read at `collect()` — by response time it has been
 * fully resolved (module/controller/action/params), which is not yet true when
 * the event fires. Nothing is resolved from the container.
 *
 * @phpstan-import-type grid_envelope from DebugBarTypes
 */
final class RouteCollector extends AbstractCollector implements Subscriber
{
    public const NAME = 'route';

    /**
     * @var string
     */
    protected string $icon = 'icon-route';

    /**
     * @var string
     */
    protected string $label = 'Route';

    /**
     * @var string
     */
    protected string $panel = 'grid';

    /**
     * @var RouterInterface|null
     */
    private ?RouterInterface $router = null;

    /**
     * @return grid_envelope
     */
    public function collect(): array
    {
        if (null === $this->router) {
            return [
                'panel' => [],
                'badge' => null,
            ];
        }

        $params = json_encode($this->router->getParams());

        return [
            'panel' => [
                'Module'     => $this->router->getModuleName(),
                'Namespace'  => $this->router->getNamespaceName(),
                'Controller' => $this->router->getControllerName(),
                'Action'     => $this->router->getActionName(),
                'Params'     => is_string($params) ? $params : '',
            ],
            'badge' => null,
        ];
    }

    /**
     * @param ManagerInterface $eventsManager
     *
     * @return void
     */
    public function subscribe(ManagerInterface $eventsManager): void
    {
        $eventsManager->attach(
            'router:matchedRoute',
            function (EventInterface $event, mixed $router): void {
                if ($router instanceof RouterInterface) {
                    $this->router = $router;
                }
            }
        );
    }
}
