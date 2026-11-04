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

use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

use function is_array;
use function is_string;

/**
 * Feeds the collectors from the events of an ADR application. The event names are
 * strings because `Phalcon\DataMapper\Pdo\Events` is not in versions before
 * `v5.19.0`. A `before` listener must not return `false`: this cancels the operation.
 */
final class Bridge
{
    private const AFTER_QUERY_EVENTS = ['dm:afterExec', 'dm:afterPerform', 'dm:afterQuery'];

    private const BEFORE_QUERY_EVENTS = ['dm:beforeExec', 'dm:beforePerform', 'dm:beforeQuery'];

    public function __construct(private readonly DebugBar $bar)
    {
    }

    public function subscribe(ManagerInterface $eventsManager): void
    {
        $collector = $this->bar->getCollectors()[DatabaseCollector::NAME] ?? null;
        if (!$collector instanceof DatabaseCollector) {
            return;
        }

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
}
