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

namespace Phalcon\Tests\Unit\DebugBar\Collector;

use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use ReflectionProperty;

final class DatabaseCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testADroppedQueryStillEndsItsStart(): void
    {
        $collector = new DatabaseCollector(1);
        $property  = new ReflectionProperty(DatabaseCollector::class, 'starts');
        $property->setAccessible(true);

        for ($index = 0; $index < 3; $index++) {
            $collector->startQuery();
            $collector->finishQuery('SELECT ' . $index);
        }

        $this->assertSame([], $property->getValue($collector));
    }

    public function testAnInfiniteBindingKeepsTheOtherBindings(): void
    {
        $collector = new DatabaseCollector();

        $collector->startQuery();
        $collector->finishQuery('SELECT * FROM t WHERE a = :a AND r = :r', ['a' => 'b', 'r' => INF]);

        $this->assertSame(
            'SELECT * FROM t WHERE a = :a AND r = :r  {"a":"b","r":0}',
            $collector->collect()['panel'][0]['message']
        );
    }

    public function testBindingsShowSlashesAndUnicodeAsTheLogsPanelDoes(): void
    {
        $collector = new DatabaseCollector();

        $collector->startQuery();
        $collector->finishQuery('SELECT * FROM t WHERE p = :p AND n = :n', ['p' => '/x/y', 'n' => 'café']);

        $this->assertSame(
            'SELECT * FROM t WHERE p = :p AND n = :n  {"p":"/x/y","n":"café"}',
            $collector->collect()['panel'][0]['message']
        );
    }

    public function testDuplicateDetectionIgnoresBindingsButNotDifferentStatements(): void
    {
        $collector = new DatabaseCollector();
        $property  = new ReflectionProperty(DatabaseCollector::class, 'queries');
        $property->setAccessible(true);
        $property->setValue(
            $collector,
            [
                ['sql' => 'SELECT * FROM users WHERE id = :id', 'bindings' => ['id' => 1], 'time' => 100000],
                ['sql' => 'SELECT * FROM users WHERE id = :id', 'bindings' => ['id' => 2], 'time' => 200000],
                ['sql' => 'SELECT * FROM users WHERE id = :id', 'bindings' => ['id' => 3], 'time' => 300000],
                [
                    'sql'      => 'SELECT * FROM users WHERE email = :email',
                    'bindings' => ['email' => 'a@b.c'],
                    'time'     => 400000,
                ],
                ['sql' => 'SELECT * FROM roles', 'bindings' => [], 'time' => 500000],
            ]
        );

        $envelope = $collector->collect();

        $this->assertSame(
            [
                ['label' => 'queries', 'value' => 5],
                ['label' => 'duplicate_runs', 'value' => 2],
                ['label' => 'total_time', 'value' => '1.5ms'],
            ],
            $envelope['summary']
        );
        $this->assertSame([3, 3, 3, null, null], array_column($envelope['panel'], 'occurrences'));
    }

    public function testEmptyCollectorHasZeroSummaryValues(): void
    {
        $envelope = (new DatabaseCollector())->collect();
        $this->assertSame(
            [
                ['label' => 'queries', 'value' => 0],
                ['label' => 'duplicate_runs', 'value' => 0],
                ['label' => 'total_time', 'value' => '0ms'],
            ],
            $envelope['summary']
        );
    }

    public function testFinishQueryMeasuresTheTimeFromStartQuery(): void
    {
        $collector = new DatabaseCollector();

        $collector->startQuery();
        $collector->finishQuery('SELECT 1');

        $property = new ReflectionProperty(DatabaseCollector::class, 'queries');
        $property->setAccessible(true);

        /** @var array<int, array{time: float|int}> $queries */
        $queries = $property->getValue($collector);
        $time    = $queries[0]['time'];

        /**
         * With no start time, `time` has the full monotonic clock value
         * (billions of nanoseconds).
         */
        $this->assertGreaterThanOrEqual(0, $time);
        $this->assertLessThan(1000000000, $time);
    }

    public function testFinishQueryWithNoBindingsShowsOnlyTheStatement(): void
    {
        $collector = new DatabaseCollector();

        $collector->startQuery();
        $collector->finishQuery('SELECT 1');

        $this->assertSame('SELECT 1', $collector->collect()['panel'][0]['message']);
    }

    public function testFinishQueryWithNoStartRecordsZeroTime(): void
    {
        $collector = new DatabaseCollector();

        $collector->finishQuery('SELECT 1');
        $collector->startQuery();
        $collector->finishQuery('SELECT 2');
        usleep(10000);
        $collector->finishQuery('SELECT 3');

        $panel = $collector->collect()['panel'];

        $this->assertSame(['SELECT 1', 'SELECT 2', 'SELECT 3'], array_column($panel, 'message'));
        $this->assertSame('0ms', $panel[0]['label']);
        $this->assertSame('0ms', $panel[2]['label']);
    }

    public function testInvalidUtf8InABindingIsReplaced(): void
    {
        $collector = new DatabaseCollector();

        $collector->startQuery();
        $collector->finishQuery('SELECT * FROM users WHERE name = :name', ['name' => "\xFF"]);

        $this->assertSame(
            'SELECT * FROM users WHERE name = :name  {"name":"' . "\u{FFFD}" . '"}',
            $collector->collect()['panel'][0]['message']
        );
    }

    public function testLabelReflectsQueryDurationInMilliseconds(): void
    {
        $collector = new DatabaseCollector();

        $property = new ReflectionProperty(DatabaseCollector::class, 'queries');
        $property->setAccessible(true);
        $property->setValue(
            $collector,
            [
                ['sql' => 'SELECT 1', 'bindings' => [], 'time' => 3456000],
            ]
        );

        $envelope = $collector->collect();
        $row      = $envelope['panel'][0];

        $this->assertArrayHasKey('label', $row);
        $this->assertSame('3.46ms', $row['label']);
        $this->assertSame('SELECT 1', $row['message']);
        $this->assertNull($row['occurrences']);
        $this->assertSame(1, $envelope['badge']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new DatabaseCollector();

        $this->assertSame('database', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testNestedQueriesPairWithTheirOwnStart(): void
    {
        $collector = new DatabaseCollector();

        $collector->startQuery();
        usleep(20000);
        $collector->startQuery();
        $collector->finishQuery('SELECT inner');
        $collector->finishQuery('SELECT outer');

        $panel = $collector->collect()['panel'];

        $this->assertSame(['SELECT inner', 'SELECT outer'], array_column($panel, 'message'));
        $this->assertGreaterThanOrEqual(20.0, (float) $panel[1]['label']);
    }

    public function testRowsAboveTheLimitAreDropped(): void
    {
        $collector = new DatabaseCollector(1);

        foreach (['SELECT 1', 'SELECT 2', 'SELECT 3'] as $sql) {
            $collector->startQuery();
            $collector->finishQuery($sql);
        }

        $envelope = $collector->collect();

        $this->assertSame(3, $envelope['badge']);
        $this->assertSame(['label' => 'queries', 'value' => 3], $envelope['summary'][0]);
        $this->assertCount(2, $envelope['panel']);
        $this->assertSame('SELECT 1', $envelope['panel'][0]['message']);
        $this->assertSame(
            ['label' => 'dropped', 'message' => '2 rows dropped', 'occurrences' => null],
            $envelope['panel'][1]
        );
    }

    public function testStartAndFinishRecordAQueryWithNoEventsManager(): void
    {
        $collector = new DatabaseCollector();

        $collector->startQuery();
        $collector->finishQuery('SELECT * FROM users WHERE id = :id', ['id' => 5]);

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame(
            'SELECT * FROM users WHERE id = :id  {"id":5}',
            $envelope['panel'][0]['message']
        );
    }

    public function testSummaryReportsQueryCountDuplicatesAndTotalDuration(): void
    {
        $collector = new DatabaseCollector();
        $property  = new ReflectionProperty(DatabaseCollector::class, 'queries');
        $property->setAccessible(true);
        $property->setValue(
            $collector,
            [
                [
                    'sql'      => 'SELECT * FROM users WHERE id = :id',
                    'bindings' => ['id' => 1],
                    'time'     => 1250000,
                ],
                [
                    'sql'      => " SELECT  *  FROM users WHERE id = :id ",
                    'bindings' => ['id' => 2],
                    'time'     => 2000000,
                ],
                [
                    'sql'      => 'SELECT * FROM roles',
                    'bindings' => [],
                    'time'     => 500000,
                ],
            ]
        );

        $envelope = $collector->collect();

        $this->assertSame(
            [
                ['label' => 'queries', 'value' => 3],
                ['label' => 'duplicate_runs', 'value' => 1],
                ['label' => 'total_time', 'value' => '3.75ms'],
            ],
            $envelope['summary']
        );
        $this->assertSame(
            [
                ['label' => '1.25ms', 'occurrences' => 2],
                ['label' => '2ms', 'occurrences' => 2],
                ['label' => '0.5ms', 'occurrences' => null],
            ],
            array_map(
                static fn (array $row): array => [
                    'label'       => $row['label'],
                    'occurrences' => $row['occurrences'],
                ],
                $envelope['panel']
            )
        );
    }
}
