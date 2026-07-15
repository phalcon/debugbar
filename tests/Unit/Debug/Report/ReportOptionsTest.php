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

namespace Phalcon\Tests\Unit\Debug\Report;

use Phalcon\Debug\Report\ReportOptions;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class ReportOptionsTest extends AbstractUnitTestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $options = new ReportOptions(
            ['request' => ['x'], 'server' => ['y']],
            true,
            false,
            true,
            'https://cdn/',
            ['var' => 1]
        );

        $this->assertSame(['request' => ['x'], 'server' => ['y']], $options->getBlacklist());
        $this->assertTrue($options->getShowBackTrace());
        $this->assertFalse($options->getShowFiles());
        $this->assertTrue($options->getShowFileFragment());
        $this->assertSame('https://cdn/', $options->getUri());
        $this->assertSame(['var' => 1], $options->getData());
    }
}
