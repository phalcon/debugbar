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
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

use function count;
use function hrtime;

/**
 * Records SQL queries by subscribing to `db:beforeQuery`/`db:afterQuery`. The
 * connection is the event's source, so the statement, bindings, and timing are
 * read straight off it — no `db` service is resolved.
 *
 * @phpstan-import-type db_query from DebugBarTypes
 * @phpstan-import-type list_envelope from DebugBarTypes
 */
final class DatabaseCollector extends AbstractCollector implements Subscriber
{
    use EncodesJson;
    use FormatsDuration;

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
     * @var list<db_query>
     */
    private array $queries = [];

    /**
     * @var int|float
     */
    private int|float $started = 0;

    /**
     * @return list_envelope
     */
    public function collect(): array
    {
        $rows = [];
        foreach ($this->queries as $query) {
            $rows[] = [
                'label'   => $this->nanosToMs($query['time']),
                'message' => $this->formatQuery($query['sql'], $query['bindings']),
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
                $this->started = hrtime(true);
            }
        );

        $eventsManager->attach(
            'db:afterQuery',
            function (EventInterface $event, mixed $connection): void {
                if ($connection instanceof AbstractAdapter) {
                    $this->queries[] = [
                        'sql'      => $connection->getSQLStatement(),
                        'bindings' => $connection->getSQLVariables(),
                        'time'     => hrtime(true) - $this->started,
                    ];
                }
            }
        );
    }

    /**
     * Appends the bound parameters (e.g. `:APL0`) to the statement so the panel
     * shows the actual values sent to the database.
     *
     * @param string                  $sql
     * @param array<array-key, mixed> $bindings
     *
     * @return string
     */
    private function formatQuery(string $sql, array $bindings): string
    {
        if ([] === $bindings) {
            return $sql;
        }

        return $sql . '  ' . $this->jsonOrEmpty($bindings);
    }
}
