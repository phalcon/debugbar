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

use Phalcon\Debug;
use Phalcon\Debug\Renderer\HtmlRenderer;
use Phalcon\Debug\Report\ExceptionReport;
use Phalcon\Support\Exception;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use ReflectionProperty;

final class OnUncaughtExceptionTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function testSupportDebugOnUncaughtException(): void
    {
        $message = uniqid('var-');
        $debug = new Debug();
        $debug->setShowBackTrace(false);
        $exception = new Exception($message, 1234);

        /**
         * The handler clears one active output-buffer level and echoes the
         * rendered HTML, so wrap the call in two levels and read the outer one.
         */
        ob_start();
        ob_start();
        $result = $debug->onUncaughtException($exception);
        /** @var string $actual */
        $actual = ob_get_clean();

        $this->assertTrue($result);
        $this->assertStringContainsString('<!DOCTYPE html>', $actual);
        $this->assertStringContainsString($message, $actual);
    }

    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2026-07-13
     */
    public function testSupportDebugOnUncaughtExceptionTogglesIsActive(): void
    {
        $renderer = new class () extends HtmlRenderer {
            public ?bool $activeDuringRender = null;

            public function render(ExceptionReport $report): string
            {
                $property = new ReflectionProperty(Debug::class, 'isActive');

                $this->activeDuringRender = (bool) $property->getValue();

                return 'captured';
            }
        };

        $isActive = new ReflectionProperty(Debug::class, 'isActive');
        $isActive->setValue(null, false);

        $debug = new Debug();
        $debug->setRenderer($renderer);
        $exception = new Exception(uniqid('var-'), 1234);

        /**
         * The handler clears one active output-buffer level and echoes the
         * rendered output, so wrap the call in two levels and read the outer.
         */
        ob_start();
        ob_start();
        $result = $debug->onUncaughtException($exception);
        /** @var string $output */
        $output = ob_get_clean();

        $activeAfter = (bool) $isActive->getValue();
        $isActive->setValue(null, false);

        $this->assertTrue($result);
        $this->assertSame('captured', $output);
        /**
         * The component blocks itself while it renders the exception.
         */
        $this->assertTrue($renderer->activeDuringRender);
        /**
         * The component unblocks itself once the exception is rendered.
         */
        $this->assertFalse($activeAfter);
    }

    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2026-06-20
     */
    public function testSupportDebugOnUncaughtExceptionWhenAlreadyActive(): void
    {
        $message   = uniqid('var-');
        $debug     = new Debug();
        $exception = new Exception($message, 1234);

        $isActive = new ReflectionProperty(Debug::class, 'isActive');
        $isActive->setValue(null, true);

        ob_start();
        ob_start();
        $result = $debug->onUncaughtException($exception);
        /** @var string $actual */
        $actual = ob_get_clean();

        $isActive->setValue(null, false);

        $this->assertFalse($result);
        $this->assertStringContainsString($message, $actual);
    }
}
