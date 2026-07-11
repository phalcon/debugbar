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

namespace Phalcon\Tests\Unit\DebugBar\Binder;

use Phalcon\Container\ContainerFactory;
use Phalcon\DebugBar\Binder\Container as ContainerBinder;
use Phalcon\DebugBar\Binder\Di as DiBinder;
use Phalcon\Di\Di;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use stdClass;

final class BinderTest extends AbstractUnitTestCase
{
    public function testContainerBinderSetsResolvesAndChecks(): void
    {
        $binder = new ContainerBinder((new ContainerFactory())->newContainer());

        $this->assertFalse($binder->has('sample'));

        $binder->set('sample', fn (): stdClass => new stdClass());

        $this->assertTrue($binder->has('sample'));
        $this->assertInstanceOf(stdClass::class, $binder->resolve('sample'));
    }

    public function testDiBinderSetsResolvesAndChecks(): void
    {
        $binder = new DiBinder(new Di());

        $this->assertFalse($binder->has('sample'));

        $binder->set('sample', fn (): stdClass => new stdClass());

        $this->assertTrue($binder->has('sample'));
        $this->assertInstanceOf(stdClass::class, $binder->resolve('sample'));
    }
}
