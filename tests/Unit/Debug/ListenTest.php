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

final class ListenTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2026-04-11
     */
    public function testSupportDebugListenExceptionsOnly(): void
    {
        $debug = new Debug();

        $errorBefore = $this->peekErrorHandler();

        $result           = $debug->listen();
        $exceptionHandler = $this->peekExceptionHandler();
        $errorAfter       = $this->peekErrorHandler();

        restore_exception_handler();

        $this->assertInstanceOf(Debug::class, $result);
        /**
         * The default first argument is `true`, so the exception handler is
         * installed and points to `Debug::onUncaughtException()`.
         */
        $this->assertBoundTo($debug, 'onUncaughtException', $exceptionHandler);
        /**
         * The default second argument is `false`, so the low severity error
         * handler is left untouched.
         */
        $this->assertSame($errorBefore, $errorAfter);
    }

    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2026-04-11
     */
    public function testSupportDebugListenLowSeverityBranch(): void
    {
        $debug = new Debug();

        $result           = $debug->listen(false, true);
        $errorHandler     = $this->peekErrorHandler();
        $exceptionHandler = $this->peekExceptionHandler();

        restore_error_handler();
        restore_exception_handler();

        $this->assertInstanceOf(Debug::class, $result);
        /**
         * The low severity branch installs both the error handler and the
         * exception handler.
         */
        $this->assertBoundTo($debug, 'onUncaughtLowSeverity', $errorHandler);
        $this->assertBoundTo($debug, 'onUncaughtException', $exceptionHandler);
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

    private function peekErrorHandler(): ?callable
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return $handler;
    }

    private function peekExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
