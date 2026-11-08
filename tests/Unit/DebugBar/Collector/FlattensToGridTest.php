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

use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\GridFixture;
use stdClass;

final class FlattensToGridTest extends AbstractUnitTestCase
{
    public function testFlattensNestedStructureIntoDottedKeys(): void
    {
        $result = (new GridFixture())->toGrid([
            'name'   => 'phalcon',
            'nested' => [
                'count' => 3,
                'ok'    => true,
                'ratio' => 1.5,
            ],
            'flag'  => false,
            'empty' => null,
            'list'  => [],
        ]);

        $this->assertSame(
            [
                'name'         => 'phalcon',
                'nested.count' => '3',
                'nested.ok'    => 'true',
                'nested.ratio' => '1.5',
                'flag'         => 'false',
                'empty'        => 'null',
                'list'         => '[]',
            ],
            $result,
        );
    }

    public function testObjectLeafShowsItsClassName(): void
    {
        $stringable = new class () {
            public function __toString(): string
            {
                return 'OBJECT';
            }
        };

        $result = (new GridFixture())->toGrid(['object' => new stdClass(), 'stringable' => $stringable]);

        $this->assertSame(['object' => 'stdClass', 'stringable' => 'class@anonymous'], $result);
    }
}
