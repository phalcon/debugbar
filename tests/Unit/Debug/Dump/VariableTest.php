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

namespace Phalcon\Tests\Unit\Debug\Dump;

use Phalcon\Debug\Dump;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Talon\Talon;

final class VariableTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function debugDumpVariableName(): void
    {
        $test = 'value';
        $dump = new Dump();

        /** @var string $contents */
        $contents = file_get_contents(
            Talon::settings()->supportPath('assets/Support/Dump/variable_name_output.txt'),
        );
        $expected = trim($contents);

        $actual = $dump->variable($test, 'super');
        $this->assertSame($expected, $actual);
    }

    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function testSupportDebugDumpVariable(): void
    {
        $test = 'value';
        $dump = new Dump();

        /** @var string $contents */
        $contents = file_get_contents(
            Talon::settings()->supportPath('assets/Support/Dump/variable_output.txt'),
        );
        $expected = trim($contents);
        $actual = $dump->variable($test);
        $this->assertSame($expected, $actual);
    }
}
