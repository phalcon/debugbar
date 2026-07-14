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

use Phalcon\DebugBar\Collector\LoggerCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

use function json_decode;

final class LoggerCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testContextIsCapturedAsJson(): void
    {
        $collector = new LoggerCollector();
        $collector->addLog('user login', 'info', ['user_id' => 42, 'ip' => '10.0.0.1']);

        $context = $collector->collect()['panel'][0]['context'];

        $this->assertSame(['user_id' => 42, 'ip' => '10.0.0.1'], json_decode($context, true));
    }

    public function testEmptyContextIsAnEmptyString(): void
    {
        $collector = new LoggerCollector();
        $collector->addLog('ping', 'debug');

        $this->assertSame('', $collector->collect()['panel'][0]['context']);
    }

    public function testLogsAccumulateWithLevelAsLabel(): void
    {
        $collector = new LoggerCollector();
        $collector->addLog('user created', 'info');
        $collector->addLog('disk full', 'error');

        $envelope = $collector->collect();

        $this->assertSame(2, $envelope['badge']);
        $this->assertSame('info', $envelope['panel'][0]['label']);
        $this->assertSame('user created', $envelope['panel'][0]['message']);
        $this->assertSame('error', $envelope['panel'][1]['label']);
        $this->assertSame('disk full', $envelope['panel'][1]['message']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new LoggerCollector();
        $collector->addLog('x', 'debug', ['k' => 'v']);

        $this->assertSame('logger', $collector->getName());
        $this->assertSame('logs', $collector->getWidget()['panel']);
        $this->assertPanelContract($collector);
    }
}
