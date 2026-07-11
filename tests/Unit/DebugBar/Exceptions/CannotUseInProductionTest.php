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

namespace Phalcon\Tests\Unit\DebugBar\Exceptions;

use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\Exceptions\DebugBarThrowable;
use Phalcon\DebugBar\Exceptions\Exception;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Throwable;

final class CannotUseInProductionTest extends AbstractUnitTestCase
{
    public function testBaseExceptionImplementsThrowableMarker(): void
    {
        $exception = new Exception('base');

        $this->assertInstanceOf(DebugBarThrowable::class, $exception);
        $this->assertInstanceOf(Throwable::class, $exception);
    }

    public function testIsDebugBarThrowableAndPackageException(): void
    {
        $exception = new CannotUseInProduction('nope');

        $this->assertInstanceOf(DebugBarThrowable::class, $exception);
        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertInstanceOf(Throwable::class, $exception);
    }
}
