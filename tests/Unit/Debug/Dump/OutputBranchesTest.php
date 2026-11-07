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
use stdClass;

final class OutputBranchesTest extends AbstractUnitTestCase
{
    public function testAlreadyListedIndentNested(): void
    {
        $dump = new Dump();

        // Within a single dump the second occurrence of the class is
        // "[already listed]"; nested at tab 2 the marker carries a four-space
        // indentation.
        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'formatValue',
            ['a' => new SampleMethods(), 'z' => new SampleMethods()],
        );

        $this->assertStringContainsString("\n    [already listed]\n", $actual);
    }

    public function testAlreadyListedMethods(): void
    {
        $dump   = new Dump();
        $actual = $dump->variables(new SampleMethods(), new SampleMethods());

        $this->assertStringContainsString('[already listed]', $actual);
    }

    public function testArrayKeyIsEscaped(): void
    {
        $actual = (new Dump())->variable(['<img src=x onerror=alert(1)>' => 'value']);

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $actual);
        $this->assertStringNotContainsString('<img', $actual);
    }

    public function testArrayLogicalConditionsAllRequired(): void
    {
        $dump = new Dump();

        // Empty name plus an empty-string key: the self-reference "continue"
        // must NOT trigger, so the value has to be printed.
        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'formatValue',
            ['' => 'keepme'],
            '',
        );

        $this->assertStringContainsString('keepme', $actual);
    }

    public function testArrayRecursionIsMarked(): void
    {
        $variable         = ['x' => 1];
        $variable['self'] = &$variable;

        $actual = (new Dump())->variable($variable);

        $this->assertStringContainsString('self</span>] => *RECURSION*', $actual);
        $this->assertSame(1, substr_count($actual, '*RECURSION*'));
    }

    public function testArraySelfReferenceContinuesNotBreaks(): void
    {
        $dump = new Dump();

        // The key matching the name is skipped, but later keys must still print.
        /** @var string $actual */
        $actual = $this->callProtectedMethod(
            $dump,
            'formatValue',
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
            'formatValue',
            new Container(),
        );

        $this->assertStringContainsString('[skipped]', $actual);
        $this->assertStringContainsString(" (\n  [skipped]\n", $actual);
        $this->assertStringContainsString(
            '<b style="color:purple">Object</b>',
            $actual,
        );
    }

    public function testDetailedObjectRecursionIsMarked(): void
    {
        $object       = new class () {
            public ?object $self = null;
        };
        $object->self = $object;

        $actual = (new Dump([], true))->variable($object);

        $this->assertStringContainsString('public</span>) = *RECURSION*', $actual);
        $this->assertSame(1, substr_count($actual, '*RECURSION*'));
    }

    public function testDetailedObjectUsesReflection(): void
    {
        $dump   = new Dump([], true);
        $actual = $dump->variable(new ClassProperties());

        $this->assertStringContainsString('protected', $actual);
        $this->assertStringContainsString('private', $actual);
    }

    public function testDetailedUninitializedPropertyIsMarked(): void
    {
        $object = new class () {
            public int $count;
        };

        $actual = (new Dump([], true))->variable($object);

        $this->assertStringContainsString(
            'count</span> (<span style="color:purple">public</span>) = [uninitialized]',
            $actual,
        );
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

    public function testObjectRecursionIsMarked(): void
    {
        $object       = new stdClass();
        $object->self = $object;

        $actual = (new Dump())->variable($object);

        $this->assertStringContainsString(
            'self</span> (<span style="color:purple">public</span>) = *RECURSION*',
            $actual,
        );
        $this->assertSame(1, substr_count($actual, '*RECURSION*'));
    }

    public function testPublicPropertyNameIsEscaped(): void
    {
        $object                                   = new stdClass();
        $object->{'<img src=x onerror=alert(1)>'} = 'value';

        $actual = (new Dump())->variable($object);

        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $actual);
        $this->assertStringNotContainsString('<img', $actual);
    }

    public function testRepeatedObjectIsNotMarkedAsRecursion(): void
    {
        $shared = new stdClass();

        $actual = (new Dump())->variable(['a' => $shared, 'b' => $shared]);

        $this->assertStringNotContainsString('*RECURSION*', $actual);
        $this->assertSame(2, substr_count($actual, '<b style="color:purple">Object</b> stdClass'));
    }

    public function testRepeatedReferenceIsNotMarkedAsRecursion(): void
    {
        $shared   = ['k' => 'shared-value'];
        $variable = ['a' => &$shared, 'b' => &$shared];

        $actual = (new Dump())->variable($variable);

        $this->assertStringNotContainsString('*RECURSION*', $actual);
        $this->assertSame(2, substr_count($actual, 'shared-value'));
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
        $actual = $this->callProtectedMethod($dump, 'formatValue', $resource, 'lbl');
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
        $actual = $this->callProtectedMethod($dump, 'formatValue', [new Di()]);

        // Nested at tab 2 the marker carries a four-space indentation.
        $this->assertStringContainsString("\n    [skipped]\n", $actual);
    }

    public function testSkipsDiInterface(): void
    {
        $actual = (new Dump())->variable(new Di());

        $this->assertStringContainsString('[skipped]', $actual);
    }
}
