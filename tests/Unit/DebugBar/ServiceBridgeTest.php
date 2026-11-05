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

namespace Phalcon\Tests\Unit\DebugBar;

use Phalcon\Db\Adapter\AbstractAdapter;
use Phalcon\DebugBar\Collector\CacheCollector;
use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\DebugBar\Collector\ViewCollector;
use Phalcon\DebugBar\Contracts\Collector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\ServiceBridge;
use Phalcon\Events\Manager;
use Phalcon\Events\ManagerInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use ReflectionProperty;
use stdClass;

use function array_column;

final class ServiceBridgeTest extends AbstractUnitTestCase
{
    public function testBarWithNoCollectorsAttachesNothing(): void
    {
        $manager = $this->createMock(ManagerInterface::class);
        $manager->expects($this->never())->method('attach');

        (new ServiceBridge(new DebugBar()))->subscribe($manager);
    }

    public function testBindingsAreAppendedToTheQuery(): void
    {
        $collector     = new DatabaseCollector();
        $eventsManager = $this->subscribed($collector);

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

    public function testCacheKeyThatIsNotAStringIsRecordedEmpty(): void
    {
        $collector     = new CacheCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('cache:afterGet', $this, ['key']);

        $this->assertSame('', $collector->collect()['panel'][0]['message']);
    }

    public function testCacheOperationsAreRecordedFromEvents(): void
    {
        $collector     = new CacheCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('cache:afterGet', $this, 'user_42');
        $eventsManager->fire('cache:afterSet', $this, 'user_42');

        $envelope = $collector->collect();

        $this->assertSame(2, $envelope['badge']);
        $this->assertSame('Get', $envelope['panel'][0]['label']);
        $this->assertSame('user_42', $envelope['panel'][0]['message']);
        $this->assertSame('Set', $envelope['panel'][1]['label']);
    }

    public function testCacheOperationsHaveTheirLabels(): void
    {
        $collector     = new CacheCollector();
        $eventsManager = $this->subscribed($collector);

        foreach (['Get', 'Set', 'Has', 'Delete'] as $operation) {
            $eventsManager->fire('cache:after' . $operation, $this, 'key');
        }

        $this->assertSame(
            ['Get', 'Set', 'Has', 'Delete'],
            array_column($collector->collect()['panel'], 'label')
        );
    }

    public function testDurationIsMeasuredBetweenBeforeAndAfterQuery(): void
    {
        $collector     = new DatabaseCollector();
        $eventsManager = $this->subscribed($collector);

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

    public function testNonAdapterConnectionIsIgnored(): void
    {
        $collector     = new DatabaseCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('db:afterQuery', new stdClass());

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testQueriesAreRecordedFromEvents(): void
    {
        $collector     = new DatabaseCollector();
        $eventsManager = $this->subscribed($collector);

        $connection = $this->createMock(AbstractAdapter::class);
        $connection->method('getSQLStatement')->willReturn('SELECT 1');
        $connection->method('getSQLVariables')->willReturn([]);

        $eventsManager->fire('db:beforeQuery', $connection);
        $eventsManager->fire('db:afterQuery', $connection);

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('SELECT 1', $envelope['panel'][0]['message']);
    }

    public function testRenderedViewsAreRecordedFromEvents(): void
    {
        $collector     = new ViewCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('view:beforeRenderView', $this, '/app/views/index.phtml');
        $eventsManager->fire('view:afterRenderView', $this);

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('/app/views/index.phtml', $envelope['panel'][0]['message']);
    }

    public function testTimeIsMeasuredBetweenBeforeAndAfterRender(): void
    {
        $collector     = new ViewCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('view:beforeRenderView', $this, '/app/views/index.phtml');
        $eventsManager->fire('view:afterRenderView', $this);

        $property = new ReflectionProperty(ViewCollector::class, 'rendered');
        $property->setAccessible(true);

        /** @var array<int, array{time: float|int}> $rendered */
        $rendered = $property->getValue($collector);
        $time     = $rendered[0]['time'];

        /**
         * The delta between two consecutive event fires is a handful of
         * nanoseconds. If the subtraction became an addition, `time` would
         * carry the full monotonic clock value (billions of nanoseconds).
         */
        $this->assertGreaterThanOrEqual(0, $time);
        $this->assertLessThan(1000000000, $time);
    }

    public function testUnmatchedAfterRenderIsIgnored(): void
    {
        $collector     = new ViewCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('view:afterRenderView', $this);

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testViewPathThatIsNotAStringIsRecordedEmpty(): void
    {
        $collector     = new ViewCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('view:beforeRenderView', $this, null);
        $eventsManager->fire('view:afterRenderView', $this);

        $this->assertSame('', $collector->collect()['panel'][0]['message']);
    }

    private function subscribed(Collector $collector): Manager
    {
        $eventsManager = new Manager();

        (new ServiceBridge((new DebugBar())->addCollector($collector)))->subscribe($eventsManager);

        return $eventsManager;
    }
}
