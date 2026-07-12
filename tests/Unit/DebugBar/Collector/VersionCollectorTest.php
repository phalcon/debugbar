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

use Phalcon\DebugBar\Collector\VersionCollector;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

use const PHP_VERSION;

final class VersionCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testCollectReportsPhpAndPhalcon(): void
    {
        $envelope = (new VersionCollector())->collect();

        $this->assertArrayHasKey('PHP', $envelope['panel']);
        $this->assertArrayHasKey('Phalcon', $envelope['panel']);
        $this->assertSame(PHP_VERSION, $envelope['panel']['PHP']);
    }

    public function testNameAndPanelContract(): void
    {
        $collector = new VersionCollector();

        $this->assertSame('version', $collector->getName());
        $this->assertSame('grid', $collector->getWidget()['panel']);
        $this->assertPanelContract($collector);
    }
}
