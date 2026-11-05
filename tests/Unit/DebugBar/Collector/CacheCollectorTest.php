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

    public function testNameAndPanelContract(): void
    {
        $collector = new CacheCollector();

        $this->assertSame('cache', $collector->getName());
        $this->assertPanelContract($collector);
    }
}
