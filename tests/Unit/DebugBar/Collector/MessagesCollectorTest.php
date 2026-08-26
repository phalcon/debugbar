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

use Phalcon\DebugBar\Collector\MessagesCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

final class MessagesCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testMessagesAccumulateAndStringify(): void
    {
        $collector = new MessagesCollector();
        $collector->addMessage('hello', 'info');
        $collector->addMessage(['a' => 1], 'debug');

        $envelope = $collector->collect();

        $this->assertSame(2, $envelope['badge']);
        $this->assertSame('info', $envelope['panel'][0]['label']);
        $this->assertSame('hello', $envelope['panel'][0]['message']);
        $this->assertSame('{"a":1}', $envelope['panel'][1]['message']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new MessagesCollector();
        $collector->addMessage('x', 'info');

        $this->assertSame('messages', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testStringifiesScalarMessages(): void
    {
        $collector = new MessagesCollector();
        $collector->addMessage(42, 'info');
        $collector->addMessage(true, 'debug');

        $panel = $collector->collect()['panel'];

        $this->assertSame('42', $panel[0]['message']);
        $this->assertSame('1', $panel[1]['message']);
    }
}
