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

use Phalcon\Debug\Report\CodeFragment;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class CodeFragmentTest extends AbstractUnitTestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $fragment = new CodeFragment('fragment', 3, 5, 9, ["<?php\n", "echo 1;\n"]);

        $this->assertSame('fragment', $fragment->getMode());
        $this->assertSame(3, $fragment->getFirstLine());
        $this->assertSame(5, $fragment->getLine());
        $this->assertSame(9, $fragment->getLastLine());
        $this->assertSame(["<?php\n", "echo 1;\n"], $fragment->getLines());
    }
}
