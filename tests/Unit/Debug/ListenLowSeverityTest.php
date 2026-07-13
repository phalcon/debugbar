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

final class ListenLowSeverityTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function testSupportDebugListenLowSeverity(): void
    {
        $debug = new Debug();

        $result           = $debug->listenLowSeverity();
        $errorHandler     = $this->peekErrorHandler();
        $exceptionHandler = $this->peekExceptionHandler();

        restore_error_handler();
        restore_exception_handler();

        $this->assertInstanceOf(Debug::class, $result);
        /**
         * The method installs the error handler pointing to
         * `Debug::onUncaughtLowSeverity()`.
         */
        $this->assertBoundTo($debug, 'onUncaughtLowSeverity', $errorHandler);
        /**
         * It also installs the exception handler pointing to
         * `Debug::onUncaughtException()`.
         */
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
