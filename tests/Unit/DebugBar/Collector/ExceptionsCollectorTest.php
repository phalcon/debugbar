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
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use RuntimeException;

final class ExceptionsCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testNameAndPanelContract(): void
    {
        $collector = new ExceptionsCollector();
        $collector->addThrowable(new RuntimeException('x'));

        $this->assertSame('exceptions', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testThrowablesAreRecorded(): void
    {
        $collector = new ExceptionsCollector();
        $collector->addThrowable(new RuntimeException('boom'));

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertSame('RuntimeException', $envelope['panel'][0]['label']);
        $this->assertStringContainsString('boom', $envelope['panel'][0]['message']);
    }
}
