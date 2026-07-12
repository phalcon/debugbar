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

final class DatabaseCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testNameAndPanelContract(): void
    {
        $collector = new DatabaseCollector();

        $this->assertSame('database', $collector->getName());
        $this->assertPanelContract($collector);
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
