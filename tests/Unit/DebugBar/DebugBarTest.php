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

use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Exceptions\Exception;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\GridCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\ListCollector;

final class DebugBarTest extends AbstractUnitTestCase
{
    public function testAddAndGetCollector(): void
    {
        $bar       = new DebugBar();
        $collector = new GridCollector();

        $bar->addCollector($collector);

        $this->assertTrue($bar->hasCollector('grid_fixture'));
        $this->assertSame($collector, $bar->getCollector('grid_fixture'));
    }

    public function testAddCollectorIsLastWriteWins(): void
    {
        $bar    = new DebugBar();
        $first  = new GridCollector();
        $second = new GridCollector();

        $bar->addCollector($first)
            ->addCollector($second);

        $this->assertSame($second, $bar->getCollector('grid_fixture'));
        $this->assertCount(1, $bar->getCollectors());
    }

    public function testCollectAggregatesEveryCollectorAndCaches(): void
    {
        $bar = new DebugBar();
        $bar->addCollector(new GridCollector())
            ->addCollector(new ListCollector());

        $result = $bar->collect();

        $this->assertArrayHasKey('grid_fixture', $result['data']);
        $this->assertArrayHasKey('list_fixture', $result['data']);
        $this->assertSame(2, $result['meta']['collectors']);
        $this->assertSame($result, $bar->getData());
    }

    public function testGetCollectorThrowsForUnknown(): void
    {
        $bar = new DebugBar();

        $this->expectException(Exception::class);

        $bar->getCollector('nope');
    }

    public function testGetDataIsEmptyBeforeCollect(): void
    {
        $bar = new DebugBar();

        $data = $bar->getData();

        $this->assertSame([], $data['data']);
        $this->assertSame([], $data['meta']);
    }

    public function testRemoveCollector(): void
    {
        $bar = new DebugBar();
        $bar->addCollector(new GridCollector());

        $bar->removeCollector('grid_fixture');

        $this->assertFalse($bar->hasCollector('grid_fixture'));
    }
}
