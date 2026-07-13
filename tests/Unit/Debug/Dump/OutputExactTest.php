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
use Phalcon\Tests\Support\Support\Dump\NestedProperties;
use Phalcon\Tests\Support\Support\Dump\SampleMethods;
use stdClass;

use function file_get_contents;

final class OutputExactTest extends AbstractUnitTestCase
{
    public function testAlreadyListedExact(): void
    {
        $dump = new Dump();

        // First pass registers the class so the second is "[already listed]".
        $this->callProtectedMethod($dump, 'output', new SampleMethods());

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', new SampleMethods());

        $this->assertSame($this->expected('dump_already_listed.txt'), $actual);
    }

    public function testDetailedNestedExact(): void
    {
        $dump = new Dump([], true);

        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'output',
            ['x' => new NestedProperties()],
        );

        $this->assertSame($this->expected('dump_detailed_nested.txt'), $actual);
    }

    public function testNestedArrayExact(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'output',
            ['a' => ['b' => 'c']],
        );

        $this->assertSame($this->expected('dump_nested_array.txt'), $actual);
    }

    public function testNestedObjectExact(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'output',
            ['w' => new SampleMethods()],
        );

        $this->assertSame($this->expected('dump_object_nested.txt'), $actual);
    }

    public function testScalarBoolean(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', true, 'lbl');

        $this->assertSame(
            'lbl <b style="color:green">Boolean</b> '
            . '(<span style="color:green">TRUE</span>)',
            $actual,
        );
    }

    public function testScalarFloat(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', 3.14, 'lbl');

        $this->assertSame(
            'lbl <b style="color:fuchsia">Float</b> '
            . '(<span style="color:fuchsia">3.14</span>)',
            $actual,
        );
    }

    public function testScalarInteger(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', 123, 'lbl');

        $this->assertSame(
            'lbl <b style="color:blue">Integer</b> '
            . '(<span style="color:blue">123</span>)',
            $actual,
        );
    }

    public function testScalarNull(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', null, 'lbl');

        $this->assertSame('lbl <b style="color:black">NULL</b>', $actual);
    }

    public function testScalarNumericString(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', '12345', 'lbl');

        $this->assertSame(
            'lbl <b style="color:navy">Numeric String</b> '
            . '(<span style="color:navy">5</span>) '
            . '"<span style="color:navy">12345</span>"',
            $actual,
        );
    }

    public function testScalarStringMultibyte(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', 'héllo', 'lbl');

        // mb_strlen === 5, strlen === 6: the length must be reported as 5.
        $this->assertSame(
            'lbl <b style="color:teal">String</b> '
            . '(<span style="color:teal">5</span>) '
            . '"<span style="color:teal">h&eacute;llo</span>"',
            $actual,
        );
    }

    public function testStdClassArrayProperty(): void
    {
        $variable        = new stdClass();
        $variable->items = ['k' => 'v'];

        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', $variable);

        $this->assertSame($this->expected('dump_stdclass_array.txt'), $actual);
    }

    public function testStdClassDetailed(): void
    {
        $variable    = new stdClass();
        $variable->x = 5;

        $dump = new Dump([], true);

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', $variable);

        $this->assertSame($this->expected('dump_stdclass_detailed.txt'), $actual);
    }

    private function expected(string $file): string
    {
        /** @var string $contents */
        $contents = file_get_contents(
            Talon::settings()->supportPath('assets/Support/Dump/' . $file),
        );

        return trim($contents);
    }
}
