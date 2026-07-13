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

use Phalcon\DebugBar\Collector\ViewCollector;
use Phalcon\Events\Manager;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use ReflectionProperty;

final class ViewCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testCollectLabelFormatsMilliseconds(): void
    {
        $collector = new ViewCollector();

        $property = new ReflectionProperty(ViewCollector::class, 'rendered');
        $property->setAccessible(true);
        $property->setValue(
            $collector,
            [
                ['path' => '/app/views/index.phtml', 'time' => 3456000],
            ]
        );

        $envelope = $collector->collect();
        $row      = $envelope['panel'][0];

        $this->assertSame(1, $envelope['badge']);
        $this->assertArrayHasKey('label', $row);
        $this->assertSame('3.46ms', $row['label']);
        $this->assertSame('/app/views/index.phtml', $row['message']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new ViewCollector();

        $this->assertSame('view', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testUnmatchedAfterRenderIsIgnored(): void
    {
        $collector     = new ViewCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $eventsManager->fire('view:afterRenderView', $this);

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testRenderedViewsAreRecordedFromEvents(): void
    {
        $collector     = new ViewCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $eventsManager->fire('view:beforeRenderView', $this, '/app/views/index.phtml');
        $eventsManager->fire('view:afterRenderView', $this);

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('/app/views/index.phtml', $envelope['panel'][0]['message']);
    }

    public function testTimeIsMeasuredBetweenBeforeAndAfterRender(): void
    {
        $collector     = new ViewCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $eventsManager->fire('view:beforeRenderView', $this, '/app/views/index.phtml');
        $eventsManager->fire('view:afterRenderView', $this);

        $property = new ReflectionProperty(ViewCollector::class, 'rendered');
        $property->setAccessible(true);

        /** @var array<int, array{time: int|float}> $rendered */
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
}
