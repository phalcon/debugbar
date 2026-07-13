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

use Phalcon\Container\Container;
use Phalcon\Debug\Dump;
use Phalcon\Di\Di;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\Support\Dump\ClassProperties;
use Phalcon\Tests\Support\Support\Dump\SampleMethods;

final class OutputBranchesTest extends AbstractUnitTestCase
{
    public function testAlreadyListedIndentNested(): void
    {
        $dump = new Dump();

        // First pass registers the class so a later dump is "[already listed]".
        $this->callProtectedMethod($dump, 'output', new SampleMethods());

        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'output',
            ['z' => new SampleMethods()],
        );

        // Nested at tab 2 the marker carries a four-space indentation.
        $this->assertStringContainsString("\n    [already listed]\n", $actual);
    }

    public function testAlreadyListedMethods(): void
    {
        $dump   = new Dump();
        $actual = $dump->variables(new SampleMethods(), new SampleMethods());

        $this->assertStringContainsString('[already listed]', $actual);
    }

    public function testArrayLogicalConditionsAllRequired(): void
    {
        $dump = new Dump();

        // Empty name plus an empty-string key: the self-reference "continue"
        // must NOT trigger, so the value has to be printed.
        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'output',
            ['' => 'keepme'],
            '',
        );

        $this->assertStringContainsString('keepme', $actual);
    }

    public function testArraySelfReferenceContinuesNotBreaks(): void
    {
        $dump = new Dump();

        // The key matching the name is skipped, but later keys must still print.
        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'output',
            ['self' => 'skipme', 'other' => 'keepme'],
            'self',
        );

        $this->assertStringContainsString('keepme', $actual);
        $this->assertStringNotContainsString('skipme', $actual);
    }

    public function testArraySelfReferenceKeyIsSkipped(): void
    {
        $key    = uniqid('var-');
        $value  = uniqid('val-');
        $dump   = new Dump();
        $actual = $dump->variable([$key => $value], $key);

        $this->assertStringContainsString($key, $actual);
        $this->assertStringNotContainsString($value, $actual);
    }

    public function testBoolean(): void
    {
        $dump = new Dump();

        $this->assertStringContainsString('TRUE', $dump->variable(true));
        $this->assertStringContainsString('FALSE', $dump->variable(false));
    }

    public function testContainerIsSkipped(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'output',
            new Container(),
        );

        $this->assertStringContainsString('[skipped]', $actual);
        $this->assertStringContainsString(" (\n  [skipped]\n", $actual);
        $this->assertStringContainsString(
            '<b style="color:purple">Object</b>',
            $actual,
        );
    }

    public function testDetailedObjectUsesReflection(): void
    {
        $dump   = new Dump([], true);
        $actual = $dump->variable(new ClassProperties());

        $this->assertStringContainsString('protected', $actual);
        $this->assertStringContainsString('private', $actual);
    }

    public function testFloat(): void
    {
        $this->assertStringContainsString('Float', (new Dump())->variable(3.14));
    }

    public function testNull(): void
    {
        $this->assertStringContainsString('NULL', (new Dump())->variable(null));
    }

    public function testNumericString(): void
    {
        $this->assertStringContainsString('Numeric String', (new Dump())->variable('12345'));
    }

    public function testResolvesParentClassAndMethodNames(): void
    {
        $actual = (new Dump())->variable(new SampleMethods());

        $this->assertStringContainsString('extends', $actual);
        $this->assertStringContainsString('ClassProperties', $actual);
        $this->assertStringContainsString('sample', $actual);
        $this->assertStringNotContainsString('{parent}', $actual);
        $this->assertStringNotContainsString(':method', $actual);
    }

    public function testResourceFallback(): void
    {
        /** @var resource $resource */
        $resource = fopen('php://memory', 'r');
        $actual   = (new Dump())->variable($resource);
        fclose($resource);

        $this->assertStringContainsString('Resource', $actual);
    }

    public function testResourceOutputBranch(): void
    {
        /** @var resource $resource */
        $resource = fopen('php://memory', 'r');

        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', $resource, 'lbl');
        fclose($resource);

        // Name prefix kept, style placeholder resolved.
        $this->assertStringStartsWith('lbl ', $actual);
        $this->assertStringNotContainsString('%style%', $actual);
        $this->assertStringContainsString('color:maroon', $actual);
    }

    public function testSkippedIndentNested(): void
    {
        $dump = new Dump();

        /** @var string $actual */
        $actual = $this->callProtectedMethod($dump, 'output', [new Di()]);

        // Nested at tab 2 the marker carries a four-space indentation.
        $this->assertStringContainsString("\n    [skipped]\n", $actual);
    }

    public function testSkipsDiInterface(): void
    {
        $actual = (new Dump())->variable(new Di());

        $this->assertStringContainsString('[skipped]', $actual);
    }
}
