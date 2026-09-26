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

use Phalcon\DebugBar\Collector\MemoryCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

use function array_keys;
use function memory_get_usage;
use function str_repeat;

final class MemoryCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testCollectReportsCurrentAndPeakMemory(): void
    {
        $collected = (new MemoryCollector())->collect();

        $this->assertSame(['Current usage', 'Peak usage'], array_keys($collected['panel']));
        $current = $collected['panel']['Current usage'];
        $peak    = $collected['panel']['Peak usage'];
        $this->assertIsString($current);
        $this->assertIsString($peak);
        $this->assertMatchesRegularExpression(
            '/^\d+(\.\d+)?(B|KB|MB|GB|TB)$/',
            $current
        );
        $this->assertMatchesRegularExpression(
            '/^\d+(\.\d+)?(B|KB|MB|GB|TB)$/',
            $peak
        );
        $this->assertSame($peak, $collected['badge']);
        if (!isset($collected['metrics'])) {
            $this->fail('Expected stable collector metrics.');
        }
        $this->assertSame(
            $current,
            $collected['metrics'][MemoryCollector::METRIC_CURRENT_USAGE]
        );
    }

    public function testCurrentUsageTracksMemoryUsedInsideReservedAllocation(): void
    {
        $reservations = [];
        while (memory_get_usage(true) - memory_get_usage(false) < 262144) {
            $reservations[] = str_repeat('x', 524288);
        }

        $collector = new MemoryCollector();
        $before    = $collector->collect()['panel']['Current usage'];
        $probe     = str_repeat('x', 65536);
        $after     = $collector->collect()['panel']['Current usage'];

        $this->assertNotSame('', $probe);
        $this->assertNotSame($before, $after);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new MemoryCollector();

        $this->assertSame('memory', $collector->getName());
        $widget = $collector->getWidget();
        $this->assertSame('Memory', $widget['label']);
        $this->assertSame('grid', $widget['panel']);
        if (!isset($widget['indicator'])) {
            $this->fail('Expected the memory indicator definition.');
        }
        $this->assertSame(
            [
                'icon'  => 'cogs',
                'label' => 'Current memory usage',
                'path'  => ['metrics', MemoryCollector::METRIC_CURRENT_USAGE],
            ],
            $widget['indicator']
        );
        $this->assertPanelContract($collector);
    }
}
