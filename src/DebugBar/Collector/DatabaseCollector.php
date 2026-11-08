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

use Phalcon\DebugBar\DebugBarTypes;

use function count;
use function hrtime;
use function is_string;
use function preg_replace;
use function trim;

/**
 * Records SQL queries. `startQuery()` starts the time, and `finishQuery()` takes
 * the statement and the bindings.
 *
 * @phpstan-import-type database_envelope from DebugBarTypes
 * @phpstan-type db_query array{sql: string, bindings: array<array-key, mixed>, time: int|float}
 */
final class DatabaseCollector extends AbstractCollector
{
    use EncodesJson;
    use FormatsDuration;
    use LimitsRows;

    public const NAME = 'database';

    protected string $label = 'Database';

    protected string $panel = 'list';

    /**
     * @var list<db_query>
     */
    private array $queries = [];

    private float | int $started = 0;

    public function __construct(private readonly int $maxRows = self::DEFAULT_MAX_ROWS)
    {
    }

    /**
     * @return database_envelope
     */
    public function collect(): array
    {
        $rows              = [];
        $occurrences       = [];
        $queryKeys         = [];
        $duplicateRuns     = 0;
        $totalNanoseconds  = 0;
        foreach ($this->queries as $index => $query) {
            $key               = $this->queryKey($query['sql']);
            $queryKeys[$index] = $key;
            $occurrences[$key] = ($occurrences[$key] ?? 0) + 1;
            if ($occurrences[$key] > 1) {
                $duplicateRuns++;
            }
            $totalNanoseconds += $query['time'];
        }

        foreach ($this->queries as $index => $query) {
            $occurrenceCount = $occurrences[$queryKeys[$index]];
            $rows[]          = [
                'label'       => $this->nanosToMs($query['time']),
                'message'     => $this->formatQuery($query['sql'], $query['bindings']),
                'occurrences' => $occurrenceCount > 1 ? $occurrenceCount : null,
            ];
        }

        if (0 < $this->droppedRows) {
            $rows[] = $this->droppedRow() + ['occurrences' => null];
        }

        $total = count($this->queries) + $this->droppedRows;

        return [
            'panel'   => $rows,
            'badge'   => $total,
            'summary' => [
                ['label' => 'queries', 'value' => $total],
                ['label' => 'duplicate_runs', 'value' => $duplicateRuns],
                ['label' => 'total_time', 'value' => $this->nanosToMs($totalNanoseconds)],
            ],
        ];
    }

    /**
     * Records a query. Call `startQuery()` first: the time starts there.
     *
     * @param array<array-key, mixed> $bindings
     */
    public function finishQuery(string $sql, array $bindings = []): void
    {
        if (!$this->acceptsRow(count($this->queries))) {
            return;
        }

        $this->queries[] = [
            'sql'      => $sql,
            'bindings' => $bindings,
            'time'     => hrtime(true) - $this->started,
        ];
    }

    public function startQuery(): void
    {
        $this->started = hrtime(true);
    }

    /**
     * Appends the bound parameters (e.g. `:APL0`) to the statement so the panel
     * shows the actual values sent to the database.
     *
     * @param array<array-key, mixed> $bindings
     */
    private function formatQuery(string $sql, array $bindings): string
    {
        if ([] === $bindings) {
            return $sql;
        }

        return $sql . '  ' . $this->jsonOrEmpty($bindings);
    }

    /**
     * Binding values are deliberately excluded: repeated executions of one
     * prepared statement are the duplication pattern this metric exposes.
     */
    private function queryKey(string $sql): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql));
        return is_string($normalized) ? $normalized : trim($sql);
    }
}
