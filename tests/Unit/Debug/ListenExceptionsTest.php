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

namespace Phalcon\Tests\Unit\Debug;

use Closure;
use Phalcon\Debug;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use ReflectionFunction;

final class ListenExceptionsTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function testSupportDebugListenExceptions(): void
    {
        $debug = new Debug();

        $result  = $debug->listenExceptions();
        $handler = $this->peekExceptionHandler();

        restore_exception_handler();

        $this->assertInstanceOf(Debug::class, $result);
        /**
         * The exception handler must be installed and must point to
         * `Debug::onUncaughtException()`.
         */
        $this->assertBoundTo($debug, 'onUncaughtException', $handler);
    }

    private function assertBoundTo(
        object $expectedThis,
        string $expectedMethod,
        ?callable $handler
    ): void {
        $this->assertInstanceOf(Closure::class, $handler);

        /** @var Closure $handler */
        $reflection = new ReflectionFunction($handler);

        $this->assertSame($expectedThis, $reflection->getClosureThis());
        $this->assertSame($expectedMethod, $reflection->getName());
    }

    private function peekExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
