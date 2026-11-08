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

    public function testFinishRenderWithNoStartIsIgnored(): void
    {
        $collector = new ViewCollector();

        $collector->finishRender();

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new ViewCollector();

        $this->assertSame('view', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testNestedRendersPairWithTheirOwnStart(): void
    {
        $collector = new ViewCollector();

        $collector->startRender('/app/views/layout.phtml');
        $collector->startRender('/app/views/partial.phtml');
        $collector->finishRender();
        $collector->finishRender();

        $this->assertSame(
            ['/app/views/partial.phtml', '/app/views/layout.phtml'],
            array_column($collector->collect()['panel'], 'message')
        );
    }

    public function testRowsAboveTheLimitAreDropped(): void
    {
        $collector = new ViewCollector(1);

        foreach (['first', 'second', 'third'] as $path) {
            $collector->startRender($path);
            $collector->finishRender();
        }

        $envelope = $collector->collect();

        $this->assertSame(3, $envelope['badge']);
        $this->assertCount(2, $envelope['panel']);
        $this->assertSame('first', $envelope['panel'][0]['message']);
        $this->assertSame(['label' => 'dropped', 'message' => '2 rows dropped'], $envelope['panel'][1]);
    }

    public function testStartAndFinishRecordARenderWithNoEventsManager(): void
    {
        $collector = new ViewCollector();

        $collector->startRender('/app/views/index.phtml');
        $collector->finishRender();

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('/app/views/index.phtml', $envelope['panel'][0]['message']);
    }
}
