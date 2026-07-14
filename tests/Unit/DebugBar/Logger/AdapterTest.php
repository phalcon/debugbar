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
use Phalcon\DebugBar\Collector\LoggerCollector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Logger\Adapter;
use Phalcon\Logger\Item;
use Phalcon\Logger\Logger;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

use function json_decode;

final class AdapterTest extends AbstractUnitTestCase
{
    public function testCloseReturnsTrue(): void
    {
        $adapter = new Adapter(new DebugBar());

        $this->assertTrue($adapter->close());
    }

    public function testLoggerContextIsForwarded(): void
    {
        $collector = new LoggerCollector();
        $bar       = new DebugBar();
        $bar->addCollector($collector);

        $logger = new Logger('debug', ['debugbar' => new Adapter($bar)]);
        $logger->info('user login', ['user_id' => 7]);

        $row = $collector->collect()['panel'][0];

        $this->assertSame('user login', $row['message']);
        $this->assertSame(['user_id' => 7], json_decode($row['context'], true));
    }

    public function testLoggingThroughRealLoggerPopulatesTheCollector(): void
    {
        $collector = new LoggerCollector();
        $bar       = new DebugBar();
        $bar->addCollector($collector);

        $logger = new Logger('debug', ['debugbar' => new Adapter($bar)]);
        $logger->info('hello');
        $logger->error('nope');

        $panel = $collector->collect()['panel'];

        $this->assertCount(2, $panel);
        $this->assertSame('info', $panel[0]['label']);
        $this->assertSame('hello', $panel[0]['message']);
        $this->assertSame('', $panel[0]['context']);
        $this->assertSame('error', $panel[1]['label']);
        $this->assertSame('nope', $panel[1]['message']);
    }

    public function testProcessForwardsMessageLevelAndContext(): void
    {
        $collector = new LoggerCollector();
        $bar       = new DebugBar();
        $bar->addCollector($collector);

        $adapter = new Adapter($bar);
        $adapter->process(
            new Item('boom', 'error', 3, new DateTimeImmutable(), ['request_id' => 'abc'])
        );

        $row = $collector->collect()['panel'][0];

        $this->assertSame('error', $row['label']);
        $this->assertSame('boom', $row['message']);
        $this->assertSame(['request_id' => 'abc'], json_decode($row['context'], true));
    }
}
