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

use Phalcon\DebugBar\Collector\HistoryCollector;
use Phalcon\DebugBar\Collector\MemoryCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

final class HistoryCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testCollectCarriesCurrentRequestMetadata(): void
    {
        $collector = new HistoryCollector('/_debugbar/open', 'POST', '/orders');

        $this->assertSame(
            [
                'panel' => [
                    'url'    => '/_debugbar/open',
                    'method' => 'POST',
                    'uri'    => '/orders',
                ],
                'badge' => null,
            ],
            $collector->collect()
        );
    }

    public function testCollectCarriesTheInternalEndpoint(): void
    {
        $collector = new HistoryCollector('/_debugbar/open');

        $this->assertSame('history', $collector->getName());
        $this->assertSame('history', $collector->getWidget()['panel']);
        $this->assertSame(
            ['panel' => ['url' => '/_debugbar/open'], 'badge' => null],
            $collector->collect()
        );
        $this->assertPanelContract($collector);
    }

    public function testMemoryIndicatorUsesAStableSemanticMetric(): void
    {
        $widget = (new HistoryCollector('/_debugbar/open'))->getWidget();
        if (!isset($widget['indicators'])) {
            $this->fail('Expected history indicator definitions.');
        }
        $indicators = $widget['indicators'];

        $this->assertSame(
            ['metrics', MemoryCollector::METRIC_CURRENT_USAGE],
            $indicators[1]['path']
        );
        $this->assertNotContains('Current usage', $indicators[1]['path']);
    }
}
