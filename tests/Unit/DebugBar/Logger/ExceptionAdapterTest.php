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
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Logger\ExceptionAdapter;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Logger\Enum;
use Phalcon\Logger\Item;
use Phalcon\Logger\Logger;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class ExceptionAdapterTest extends AbstractUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Debug::setBar(null);
    }

    protected function tearDown(): void
    {
        Debug::setBar(null);

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function errorLevelProvider(): array
    {
        return [
            'emergency' => [Enum::EMERGENCY],
            'alert'     => [Enum::ALERT],
            'critical'  => [Enum::CRITICAL],
            'error'     => [Enum::ERROR],
        ];
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function lowerLevelProvider(): array
    {
        return [
            'warning' => [Enum::WARNING],
            'notice'  => [Enum::NOTICE],
            'info'    => [Enum::INFO],
            'debug'   => [Enum::DEBUG],
            'custom'  => [Enum::CUSTOM],
        ];
    }

    public function testAdapterCreatedBeforeTheBarBootsForwardsToTheBar(): void
    {
        $logger = new Logger('debug', ['debugbar' => new ExceptionAdapter()]);

        $collector = new ExceptionsCollector();
        $bar       = (new DebugBar())->addCollector($collector);
        Debug::setBar($bar);
        $exception = new RuntimeException('boom');
        $logger->error('failed', ['exception' => $exception]);

        $this->assertSame(1, $collector->collect()['badge']);
        $this->assertSame($exception, $bar->getLastException());
    }

    #[DataProvider('errorLevelProvider')]
    public function testAnExceptionAtErrorLevelOrHigherIsTheLastException(int $level): void
    {
        $collector = new ExceptionsCollector();
        $bar       = (new DebugBar())->addCollector($collector);
        Debug::setBar($bar);
        $exception = new RuntimeException('boom');

        (new ExceptionAdapter())->process(
            new Item('failed', 'level', $level, new DateTimeImmutable(), ['exception' => $exception])
        );

        $this->assertSame(1, $collector->collect()['badge']);
        $this->assertSame($exception, $bar->getLastException());
    }

    /**
     * A handled exception that is logged below error level must not open the
     * debug page of an ADR application.
     */
    #[DataProvider('lowerLevelProvider')]
    public function testAnExceptionBelowErrorLevelShowsInThePanelButIsNotTheLastException(int $level): void
    {
        $collector = new ExceptionsCollector();
        $bar       = (new DebugBar())->addCollector($collector);
        Debug::setBar($bar);

        (new ExceptionAdapter())->process(
            new Item('handled', 'level', $level, new DateTimeImmutable(), ['exception' => new RuntimeException('boom')])
        );

        $this->assertSame(1, $collector->collect()['badge']);
        $this->assertNull($bar->getLastException());
    }

    public function testAnExceptionBelowErrorLevelWithNoExceptionsCollectorIsIgnored(): void
    {
        $bar = new DebugBar();
        Debug::setBar($bar);

        $exception = new RuntimeException('boom');

        (new ExceptionAdapter())->process(
            new Item('handled', 'warning', Enum::WARNING, new DateTimeImmutable(), ['exception' => $exception])
        );

        $this->assertNull($bar->getLastException());
    }

    public function testCloseReturnsTrue(): void
    {
        $adapter = new ExceptionAdapter();

        $this->assertTrue($adapter->close());
    }

    public function testContextWithNoThrowableIsIgnored(): void
    {
        $collector = new ExceptionsCollector();
        Debug::setBar((new DebugBar())->addCollector($collector));
        $adapter = new ExceptionAdapter();

        $adapter->process(new Item('one', 'error', 3, new DateTimeImmutable()));
        $adapter->process(new Item('two', 'error', 3, new DateTimeImmutable(), ['exception' => 'text']));

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testExceptionLoggedThroughRealLoggerReachesTheCollector(): void
    {
        $collector = new ExceptionsCollector();
        Debug::setBar((new DebugBar())->addCollector($collector));
        $logger = new Logger('debug', ['debugbar' => new ExceptionAdapter()]);

        $logger->error('failed', ['exception' => new RuntimeException('boom')]);

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('RuntimeException', $envelope['panel'][0]['label']);
        $this->assertStringStartsWith('boom (', $envelope['panel'][0]['message']);
    }

    public function testExceptionShowsOnlyInTheExceptionsCollector(): void
    {
        $logs = new LoggerCollector(new Redactor());
        Debug::setBar((new DebugBar())->addCollector($logs)->addCollector(new ExceptionsCollector()));

        (new ExceptionAdapter())->process(
            new Item('failed', 'error', 3, new DateTimeImmutable(), ['exception' => new RuntimeException('boom')])
        );

        $this->assertSame([], $logs->collect()['panel']);
    }

    public function testProcessDoesNothingWhenNoBarIsActive(): void
    {
        $adapter = new ExceptionAdapter();

        $adapter->process(
            new Item('failed', 'error', 3, new DateTimeImmutable(), ['exception' => new RuntimeException('boom')])
        );

        $this->assertNull(Debug::getBar());
    }
}
