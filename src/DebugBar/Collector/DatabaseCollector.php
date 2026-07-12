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

use Phalcon\Db\Adapter\AbstractAdapter;
use Phalcon\DebugBar\Contracts\Subscriber;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

use function count;
use function microtime;
use function round;

/**
 * Records SQL queries by subscribing to `db:beforeQuery`/`db:afterQuery`. The
 * connection is the event's source, so the statement, bindings, and timing are
 * read straight off it — no `db` service is resolved.
 */
final class DatabaseCollector extends AbstractCollector implements Subscriber
{
    public const NAME = 'database';

    /**
     * @var string
     */
    protected string $icon = 'icon-database';

    /**
     * @var string
     */
    protected string $label = 'Database';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var list<array{sql: string, bindings: array<array-key, mixed>, time: float}>
     */
    private array $queries = [];

    /**
     * @var float
     */
    private float $started = 0.0;

    /**
     * @return array{panel: list<array{label: string, message: string}>, badge: scalar|null}
     */
    public function collect(): array
    {
        $rows = [];
        foreach ($this->queries as $query) {
            $rows[] = [
                'label'   => round($query['time'] * 1000, 2) . 'ms',
                'message' => $query['sql'],
            ];
        }

        return [
            'panel' => $rows,
            'badge' => count($this->queries),
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
            'db:beforeQuery',
            function (): void {
                $this->started = microtime(true);
            }
        );

        $eventsManager->attach(
            'db:afterQuery',
            function (EventInterface $event, mixed $connection): void {
                if ($connection instanceof AbstractAdapter) {
                    $this->queries[] = [
                        'sql'      => $connection->getSQLStatement(),
                        'bindings' => $connection->getSQLVariables(),
                        'time'     => microtime(true) - $this->started,
                    ];
                }
            }
        );
    }
}
