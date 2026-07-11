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
use stdClass;

use function file_get_contents;

final class VariablesTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function testSupportDebugDumpVariables(): void
    {
        $test1 = 'string';
        $test2 = ['key' => 'value'];
        $test3 = new stdClass();

        $dump = new Dump();

        /** @var string $contents */
        $contents = file_get_contents(
            Talon::settings()->supportPath('assets/Support/Dump/variables_output.txt'),
        );
        $expected = trim($contents);
        $actual = $dump->variables($test1, $test2, $test3);
        $this->assertSame($expected, $actual);
    }
}
