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

final class ViewCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

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
}
