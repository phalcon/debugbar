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
use Phalcon\Events\Manager;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

final class CacheCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testCacheOperationsAreRecordedFromEvents(): void
    {
        $collector     = new CacheCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $eventsManager->fire('cache:afterGet', $this, 'user_42');
        $eventsManager->fire('cache:afterSet', $this, 'user_42');

        $envelope = $collector->collect();

        $this->assertSame(2, $envelope['badge']);
        $this->assertSame('Get', $envelope['panel'][0]['label']);
        $this->assertSame('user_42', $envelope['panel'][0]['message']);
        $this->assertSame('Set', $envelope['panel'][1]['label']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new CacheCollector();

        $this->assertSame('cache', $collector->getName());
        $this->assertPanelContract($collector);
    }
}
