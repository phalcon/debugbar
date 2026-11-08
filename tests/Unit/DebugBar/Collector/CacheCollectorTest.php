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

use Phalcon\DebugBar\Collector\CacheCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

final class CacheCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testAddOperationRecordsWithNoEventsManager(): void
    {
        $collector = new CacheCollector();

        $collector->addOperation('Get', 'user_42');

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame([['label' => 'Get', 'message' => 'user_42']], $envelope['panel']);
    }

    public function testDefaultLimitKeepsAThousandRows(): void
    {
        $collector = new CacheCollector();

        for ($index = 0; $index <= 1000; $index++) {
            $collector->addOperation('Get', 'key_' . $index);
        }

        $envelope = $collector->collect();

        $this->assertSame(1001, $envelope['badge']);
        $this->assertCount(1001, $envelope['panel']);
        $this->assertSame(['label' => 'Get', 'message' => 'key_999'], $envelope['panel'][999]);
        $this->assertSame(['label' => 'dropped', 'message' => '1 row dropped'], $envelope['panel'][1000]);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new CacheCollector();

        $this->assertSame('cache', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testRowsAboveTheLimitAreDropped(): void
    {
        $collector = new CacheCollector(1);

        $collector->addOperation('Get', 'first');
        $collector->addOperation('Get', 'second');
        $collector->addOperation('Set', 'third');

        $envelope = $collector->collect();

        $this->assertSame(3, $envelope['badge']);
        $this->assertSame(
            [
                ['label' => 'Get', 'message' => 'first'],
                ['label' => 'dropped', 'message' => '2 rows dropped'],
            ],
            $envelope['panel']
        );
    }
}
