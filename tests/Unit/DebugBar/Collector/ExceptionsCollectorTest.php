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

use Phalcon\DebugBar\Collector\ExceptionsCollector;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\Events\Manager;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use RuntimeException;

final class ExceptionsCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testDispatchExceptionsAreAutoRecorded(): void
    {
        $collector     = new ExceptionsCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $eventsManager->fire('dispatch:beforeException', $this, new RuntimeException('dispatched'));

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertStringContainsString('dispatched', $envelope['panel'][0]['message']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new ExceptionsCollector();
        $collector->addThrowable(new RuntimeException('x'));

        $this->assertSame('exceptions', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testNamespacedThrowableLabelIsShortClass(): void
    {
        $collector = new ExceptionsCollector();
        $collector->addThrowable(new CannotUseInProduction('nope'));

        $envelope = $collector->collect();

        $this->assertSame('CannotUseInProduction', $envelope['panel'][0]['label']);
    }

    public function testNonThrowableEventPayloadIsIgnored(): void
    {
        $collector     = new ExceptionsCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $eventsManager->fire('dispatch:beforeException', $this, 'not-a-throwable');

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testThrowableMessageFormat(): void
    {
        $collector = new ExceptionsCollector();
        $throwable = new RuntimeException('boom');
        $collector->addThrowable($throwable);

        $expected = $throwable->getMessage()
            . ' (' . $throwable->getFile() . ':' . $throwable->getLine() . ')';

        $this->assertSame($expected, $collector->collect()['panel'][0]['message']);
    }

    public function testThrowablesAreRecorded(): void
    {
        $collector = new ExceptionsCollector();
        $collector->addThrowable(new RuntimeException('boom'));

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('RuntimeException', $envelope['panel'][0]['label']);
        $this->assertStringContainsString('boom', $envelope['panel'][0]['message']);
        $this->assertArrayHasKey('trace', $envelope['panel'][0]);
    }
}
