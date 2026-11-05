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

namespace Phalcon\DebugBar;

use Phalcon\Db\Adapter\AbstractAdapter;
use Phalcon\DebugBar\Collector\CacheCollector;
use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\DebugBar\Collector\ViewCollector;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

use function is_string;

/**
 * Feeds the collectors from the events of the Phalcon services: `db:*`, `view:*`
 * and `cache:*`. These services fire their events in any application, so `Core`
 * subscribes this class. The data comes from the event: no service is resolved.
 * A `before` listener must not return `false`: this cancels the operation.
 */
final class ServiceBridge
{
    private const CACHE_EVENTS = [
        'cache:afterGet'    => 'Get',
        'cache:afterSet'    => 'Set',
        'cache:afterHas'    => 'Has',
        'cache:afterDelete' => 'Delete',
    ];

    public function __construct(private readonly DebugBar $bar)
    {
    }

    public function subscribe(ManagerInterface $eventsManager): void
    {
        $collectors = $this->bar->getCollectors();

        $cache = $collectors[CacheCollector::NAME] ?? null;
        if ($cache instanceof CacheCollector) {
            $this->subscribeCache($eventsManager, $cache);
        }

        $database = $collectors[DatabaseCollector::NAME] ?? null;
        if ($database instanceof DatabaseCollector) {
            $this->subscribeDatabase($eventsManager, $database);
        }

        $view = $collectors[ViewCollector::NAME] ?? null;
        if ($view instanceof ViewCollector) {
            $this->subscribeView($eventsManager, $view);
        }
    }

    private function subscribeCache(ManagerInterface $eventsManager, CacheCollector $collector): void
    {
        foreach (self::CACHE_EVENTS as $eventName => $operation) {
            $eventsManager->attach(
                $eventName,
                function (EventInterface $event, mixed $cache, mixed $key) use ($collector, $operation): void {
                    $collector->addOperation($operation, is_string($key) ? $key : '');
                }
            );
        }
    }

    private function subscribeDatabase(ManagerInterface $eventsManager, DatabaseCollector $collector): void
    {
        $eventsManager->attach(
            'db:beforeQuery',
            function () use ($collector): void {
                $collector->startQuery();
            }
        );

        $eventsManager->attach(
            'db:afterQuery',
            function (EventInterface $event, mixed $connection) use ($collector): void {
                if ($connection instanceof AbstractAdapter) {
                    $collector->finishQuery(
                        $connection->getSQLStatement(),
                        $connection->getSQLVariables()
                    );
                }
            }
        );
    }

    private function subscribeView(ManagerInterface $eventsManager, ViewCollector $collector): void
    {
        $eventsManager->attach(
            'view:beforeRenderView',
            function (EventInterface $event, mixed $view, mixed $path) use ($collector): void {
                $collector->startRender(is_string($path) ? $path : '');
            }
        );

        $eventsManager->attach(
            'view:afterRenderView',
            function () use ($collector): void {
                $collector->finishRender();
            }
        );
    }
}
