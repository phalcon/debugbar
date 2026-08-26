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

use Phalcon\Db\Adapter\AbstractAdapter;
use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\Events\Manager;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use ReflectionProperty;
use stdClass;

final class DatabaseCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testBindingsAreAppendedToTheQuery(): void
    {
        $collector     = new DatabaseCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $connection = $this->createMock(AbstractAdapter::class);
        $connection->method('getSQLStatement')->willReturn('SELECT * FROM users WHERE id = :APL0');
        $connection->method('getSQLVariables')->willReturn(['APL0' => 5]);

        $eventsManager->fire('db:beforeQuery', $connection);
        $eventsManager->fire('db:afterQuery', $connection);

        $message = $collector->collect()['panel'][0]['message'];

        $this->assertSame(
            'SELECT * FROM users WHERE id = :APL0  {"APL0":5}',
            $message
        );
    }

    public function testDurationIsMeasuredBetweenBeforeAndAfterQuery(): void
    {
        $collector     = new DatabaseCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $connection = $this->createMock(AbstractAdapter::class);
        $connection->method('getSQLStatement')->willReturn('SELECT 1');
        $connection->method('getSQLVariables')->willReturn([]);

        $eventsManager->fire('db:beforeQuery', $connection);
        $eventsManager->fire('db:afterQuery', $connection);

        $property = new ReflectionProperty(DatabaseCollector::class, 'queries');
        $property->setAccessible(true);

        /** @var array<int, array{time: float|int}> $queries */
        $queries = $property->getValue($collector);
        $time    = $queries[0]['time'];

        /**
         * The delta between two consecutive event fires is a handful of
         * nanoseconds. If `db:beforeQuery` never seeded `started`, or the
         * subtraction became an addition, `time` would carry the full monotonic
         * clock value (billions of nanoseconds) instead.
         */
        $this->assertGreaterThanOrEqual(0, $time);
        $this->assertLessThan(1000000000, $time);
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
        $this->assertSame(1, $envelope['badge']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new DatabaseCollector();

        $this->assertSame('database', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testNonAdapterConnectionIsIgnored(): void
    {
        $collector     = new DatabaseCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $eventsManager->fire('db:afterQuery', new stdClass());

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testQueriesAreRecordedFromEvents(): void
    {
        $collector      = new DatabaseCollector();
        $eventsManager  = new Manager();
        $collector->subscribe($eventsManager);

        $connection = $this->createMock(AbstractAdapter::class);
        $connection->method('getSQLStatement')->willReturn('SELECT 1');
        $connection->method('getSQLVariables')->willReturn([]);

        $eventsManager->fire('db:beforeQuery', $connection);
        $eventsManager->fire('db:afterQuery', $connection);

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('SELECT 1', $envelope['panel'][0]['message']);
    }
}
