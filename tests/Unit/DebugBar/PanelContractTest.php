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

use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\CodeCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\GridCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\HtmlCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\ListCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\MalformedGridCollector;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use PHPUnit\Framework\AssertionFailedError;

final class PanelContractTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testCodePanelConforms(): void
    {
        $this->assertPanelContract(new CodeCollector());
    }

    public function testGridPanelConforms(): void
    {
        $this->assertPanelContract(new GridCollector());
    }

    public function testHtmlPanelConforms(): void
    {
        $this->assertPanelContract(new HtmlCollector());
    }

    public function testListPanelConforms(): void
    {
        $this->assertPanelContract(new ListCollector());
    }

    public function testMalformedPanelIsRejected(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->assertPanelContract(new MalformedGridCollector());
    }
}
