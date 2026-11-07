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

use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\DebugBar\Collector\EncodesJson;
use Phalcon\DebugBar\Collector\RouteCollector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

use function is_array;
use function is_string;
use function json_decode;

/**
 * Feeds the collectors from the events of an ADR application. The event names are
 * strings because `Phalcon\DataMapper\Pdo\Events` is not in versions before
 * `v5.19.0`. A `before` listener must not return `false`: this cancels the operation.
 * The application fires the route event after the middleware, so the request has
 * all its attributes at this event. The attributes go through the redactor.
 */
final class Bridge
{
    use EncodesJson;

    private const AFTER_QUERY_EVENTS = ['dm:afterExec', 'dm:afterPerform', 'dm:afterQuery'];

    private const BEFORE_QUERY_EVENTS = ['dm:beforeExec', 'dm:beforePerform', 'dm:beforeQuery'];

    private const ROUTE_EVENT = 'adr:beforeExecuteAction';

    public function __construct(
        private readonly DebugBar $bar,
        private readonly Redactor $redactor = new Redactor()
    ) {
    }

    public function subscribe(ManagerInterface $eventsManager): void
    {
        $collectors = $this->bar->getCollectors();

        $database = $collectors[DatabaseCollector::NAME] ?? null;
        if ($database instanceof DatabaseCollector) {
            $this->subscribeDatabase($eventsManager, $database);
        }

        $route = $collectors[RouteCollector::NAME] ?? null;
        if ($route instanceof RouteCollector) {
            $this->subscribeRoute($eventsManager, $route);
        }
    }

    private function subscribeDatabase(ManagerInterface $eventsManager, DatabaseCollector $collector): void
    {
        foreach (self::BEFORE_QUERY_EVENTS as $eventName) {
            $eventsManager->attach(
                $eventName,
                function () use ($collector): void {
                    $collector->startQuery();
                }
            );
        }

        foreach (self::AFTER_QUERY_EVENTS as $eventName) {
            $eventsManager->attach(
                $eventName,
                function (EventInterface $event, mixed $connection, mixed $data) use ($collector): void {
                    if (!is_array($data) || !is_string($data['statement'] ?? null)) {
                        return;
                    }

                    $values = $data['values'] ?? [];
                    $collector->finishQuery($data['statement'], is_array($values) ? $values : []);
                }
            );
        }
    }

    private function subscribeRoute(ManagerInterface $eventsManager, RouteCollector $collector): void
    {
        $eventsManager->attach(
            self::ROUTE_EVENT,
            function (EventInterface $event, object $action, mixed $request) use ($collector): void {
                if ($request instanceof AttributeRequest) {
                    /**
                     * Change the objects to arrays first, so that the redactor
                     * sees their keys
                     */
                    $attributes = json_decode($this->jsonOrEmpty($request->getAttributes()->all()), true);

                    $collector->setRoute(
                        [
                            'Action'     => $action::class,
                            'Attributes' => $this->jsonOrEmpty(
                                $this->redactor->redact(is_array($attributes) ? $attributes : [])
                            ),
                        ]
                    );
                }
            }
        );
    }
}
