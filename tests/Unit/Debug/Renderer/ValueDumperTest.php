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

namespace Phalcon\Tests\Unit\Debug\Renderer;

use Phalcon\Debug\Renderer\ValueDumper;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\DumpableFixture;
use stdClass;

final class ValueDumperTest extends AbstractUnitTestCase
{
    public function testDumpArrayBranches(): void
    {
        $dumper = new ValueDumper();

        $resource = fopen('php://memory', 'r');
        if (false === $resource) {
            self::fail('Unable to open the in-memory resource.');
        }

        $this->assertNull($dumper->dumpArray([]));
        $this->assertSame('10', $dumper->dumpArray(range(1, 10)));
        $this->assertSame('12', $dumper->dumpArray(range(1, 12)));

        $dump = $dumper->dumpArray(['', 5, [1], new stdClass(), null, $resource]);

        /** @var string $dump */
        $this->assertStringContainsString('[0] =&gt; (empty string)', $dump);
        $this->assertStringContainsString('[1] =&gt; 5', $dump);
        $this->assertStringContainsString('[2] =&gt; Array([0] =&gt; 1)', $dump);
        $this->assertStringContainsString('[3] =&gt; Object(stdClass)', $dump);
        $this->assertStringContainsString('[4] =&gt; null', $dump);

        fclose($resource);

        $this->assertStringContainsString('resource (closed)', (string) $dumper->dumpArray([$resource]));
    }

    public function testDumpBranches(): void
    {
        $dumper = new ValueDumper();

        $object = new class () {
            /**
             * @return array<array-key, mixed>
             */
            public function dump(): array
            {
                return ['k' => 'v'];
            }
        };

        $resource = fopen('php://memory', 'r');
        if (false === $resource) {
            self::fail('Unable to open the in-memory resource.');
        }

        $this->assertSame('true', $dumper->dump(true));
        $this->assertSame('false', $dumper->dump(false));
        $this->assertSame('42', $dumper->dump(42));
        $this->assertSame('null', $dumper->dump(null));
        $this->assertSame('resource', $dumper->dump($resource));
        $this->assertSame('Object(stdClass)', $dumper->dump(new stdClass()));
        $this->assertStringContainsString('a&lt;b', $dumper->dump('a<b'));
        $this->assertSame("a\\nb", $dumper->dump("a\nb"));
        $this->assertStringContainsString('Array(', $dumper->dump([1, 2]));
        $this->assertStringContainsString('=&gt;', $dumper->dump($object));

        $this->assertSame('Array([0] =&gt; 1, [1] =&gt; 2)', $dumper->dump([1, 2]));
        $this->assertSame(
            'Array([0] =&gt; Array([0] =&gt; Array([0] =&gt; Array())))',
            $dumper->dump([[[['deep']]]])
        );
        $this->assertSame(
            'Object(' . DumpableFixture::class . ': [alpha] =&gt; beta)',
            $dumper->dump(new DumpableFixture())
        );

        fclose($resource);
    }

    public function testEscapeRendersNewlinesLiterally(): void
    {
        $dumper = new ValueDumper();

        $this->assertSame('a&lt;b', $dumper->escape('a<b'));
        $this->assertSame("line\\nbreak", $dumper->escape("line\nbreak"));
    }
}
