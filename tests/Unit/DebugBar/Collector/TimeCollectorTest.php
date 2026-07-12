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

use Phalcon\DebugBar\Collector\TimeCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

use function array_column;

final class TimeCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testMeasuresAreRecordedAlongsideRequest(): void
    {
        $collector = new TimeCollector();
        $collector->startMeasure('boot', 'Boot');
        $collector->stopMeasure('boot');

        $labels = array_column($collector->collect()['panel'], 'label');

        $this->assertContains('Request', $labels);
        $this->assertContains('Boot', $labels);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new TimeCollector();
        $collector->startMeasure('x');

        $this->assertSame('time', $collector->getName());
        $this->assertPanelContract($collector);
    }
}
