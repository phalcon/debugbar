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

namespace Phalcon\Tests\Unit\DebugBar;

use Phalcon\DebugBar\Collector\LoggerCollector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\RecordingExceptionsCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\RecordingMessagesCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\RecordingTimeCollector;
use RuntimeException;

use function json_decode;

final class DebugBarConvenienceTest extends AbstractUnitTestCase
{
    public function testAddExceptionDelegatesToExceptionsCollector(): void
    {
        $exceptions = new RecordingExceptionsCollector();
        $bar        = new DebugBar();
        $bar->addCollector($exceptions);

        $bar->addException(new RuntimeException('boom'));

        $this->assertCount(1, $exceptions->getThrowables());
    }

    public function testAddLogDelegatesToLoggerCollector(): void
    {
        $logger = new LoggerCollector();
        $bar    = new DebugBar();
        $bar->addCollector($logger);

        $bar->addLog('hello', 'info')
            ->addLog('boom', 'error', ['code' => 42]);

        $panel = $logger->collect()['panel'];

        $this->assertCount(2, $panel);
        $this->assertSame(['label' => 'info', 'message' => 'hello', 'context' => ''], $panel[0]);
        $this->assertSame('error', $panel[1]['label']);
        $this->assertSame(['code' => 42], json_decode($panel[1]['context'], true));
    }

    public function testConvenienceMethodsNoOpWhenCollectorsAbsent(): void
    {
        $bar = new DebugBar();

        $this->assertSame(
            $bar,
            $bar->message('x')
                ->info('a', 'b')
                ->debug('c')
                ->notice('d')
                ->warning('e')
                ->error('f')
                ->addLog('g', 'info')
                ->startMeasure('boot')
                ->stopMeasure('boot')
                ->addException(new RuntimeException('boom'))
        );
    }

    public function testMessageAndLevelsDelegateToMessagesCollector(): void
    {
        $messages = new RecordingMessagesCollector();
        $bar      = new DebugBar();
        $bar->addCollector($messages);

        $bar->message('hello', 'notice')
            ->info('a', 'b')
            ->error('boom');

        $recorded = $messages->getMessages();

        $this->assertCount(4, $recorded);
        $this->assertSame(['message' => 'hello', 'label' => 'notice'], $recorded[0]);
        $this->assertSame('info', $recorded[1]['label']);
        $this->assertSame('info', $recorded[2]['label']);
        $this->assertSame('error', $recorded[3]['label']);
    }

    public function testStartAndStopMeasureDelegateToTimeCollector(): void
    {
        $time = new RecordingTimeCollector();
        $bar  = new DebugBar();
        $bar->addCollector($time);

        $bar->startMeasure('boot', 'Boot')
            ->stopMeasure('boot');

        $measures = $time->getMeasures();

        $this->assertCount(2, $measures);
        $this->assertSame('start', $measures[0]['action']);
        $this->assertSame('Boot', $measures[0]['label']);
        $this->assertSame('stop', $measures[1]['action']);
    }
}
