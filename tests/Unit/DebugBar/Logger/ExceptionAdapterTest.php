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

namespace Phalcon\Tests\Unit\DebugBar\Logger;

use DateTimeImmutable;
use Phalcon\DebugBar\Collector\ExceptionsCollector;
use Phalcon\DebugBar\Collector\LoggerCollector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Logger\ExceptionAdapter;
use Phalcon\Logger\Item;
use Phalcon\Logger\Logger;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use RuntimeException;

final class ExceptionAdapterTest extends AbstractUnitTestCase
{
    public function testCloseReturnsTrue(): void
    {
        $adapter = new ExceptionAdapter(new DebugBar());

        $this->assertTrue($adapter->close());
    }

    public function testContextWithNoThrowableIsIgnored(): void
    {
        $collector = new ExceptionsCollector();
        $adapter   = new ExceptionAdapter((new DebugBar())->addCollector($collector));

        $adapter->process(new Item('one', 'error', 3, new DateTimeImmutable()));
        $adapter->process(new Item('two', 'error', 3, new DateTimeImmutable(), ['exception' => 'text']));

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testExceptionLoggedThroughRealLoggerReachesTheCollector(): void
    {
        $collector = new ExceptionsCollector();
        $bar       = (new DebugBar())->addCollector($collector);
        $logger    = new Logger('debug', ['debugbar' => new ExceptionAdapter($bar)]);

        $logger->error('failed', ['exception' => new RuntimeException('boom')]);

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('RuntimeException', $envelope['panel'][0]['label']);
        $this->assertStringStartsWith('boom (', $envelope['panel'][0]['message']);
    }

    public function testExceptionShowsOnlyInTheExceptionsCollector(): void
    {
        $logs = new LoggerCollector();
        $bar  = (new DebugBar())->addCollector($logs)->addCollector(new ExceptionsCollector());

        (new ExceptionAdapter($bar))->process(
            new Item('failed', 'error', 3, new DateTimeImmutable(), ['exception' => new RuntimeException('boom')])
        );

        $this->assertSame([], $logs->collect()['panel']);
    }

    public function testProcessDoesNothingWhenBarIsNull(): void
    {
        $adapter = new ExceptionAdapter();

        $adapter->process(
            new Item('failed', 'error', 3, new DateTimeImmutable(), ['exception' => new RuntimeException('boom')])
        );

        $this->assertTrue($adapter->close());
    }
}
