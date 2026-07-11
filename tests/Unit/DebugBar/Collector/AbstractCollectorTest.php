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

use Phalcon\DebugBar\Contracts\Collector;
use Phalcon\DebugBar\Contracts\Renderable;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\GridCollector;

final class AbstractCollectorTest extends AbstractUnitTestCase
{
    public function testCollectReturnsTheEnvelope(): void
    {
        $collector = new GridCollector();

        $envelope = $collector->collect();

        $this->assertArrayHasKey('panel', $envelope);
        $this->assertArrayHasKey('badge', $envelope);
        $this->assertSame(2, $envelope['badge']);
    }

    public function testGetNameReturnsTheNameConstant(): void
    {
        $collector = new GridCollector();

        $this->assertSame('grid_fixture', $collector->getName());
        $this->assertSame(GridCollector::NAME, $collector->getName());
    }

    public function testGetWidgetAssemblesFromMembers(): void
    {
        $collector = new GridCollector();

        $widget = $collector->getWidget();

        $this->assertSame('Grid', $widget['label']);
        $this->assertSame('icon-grid', $widget['icon']);
        $this->assertSame('grid', $widget['panel']);
    }

    public function testImplementsTheContracts(): void
    {
        $collector = new GridCollector();

        $this->assertInstanceOf(Renderable::class, $collector);
        $this->assertInstanceOf(Collector::class, $collector);
    }
}
