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
use RuntimeException;

final class OnUncaughtExceptionTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function testSupportDebugOnUncaughtException(): void
    {
        $message = uniqid('var-');

        /**
         * The handler discards the buffers that start after the component is
         * created and echoes the rendered HTML, so create it inside the buffer
         * that the test reads.
         */
        ob_start();
        $debug = new Debug();
        $debug->setShowBackTrace(false);
        $exception = new Exception($message, 1234);

        ob_start();
        $result = $debug->onUncaughtException($exception);
        /** @var string $actual */
        $actual = ob_get_clean();

        $this->assertTrue($result);
        $this->assertStringContainsString('<!DOCTYPE html>', $actual);
        $this->assertStringContainsString($message, $actual);
    }

    public function testSupportDebugOnUncaughtExceptionDiscardsAllNestedBuffers(): void
    {
        $level = ob_get_level();

        /**
         * The handler discards the buffers that start after the component
         * is created, so create it inside the buffer that the test reads.
         */
        ob_start();
        $debug = new Debug();
        $debug->setShowBackTrace(false);

        ob_start();
        echo 'layout-output';
        ob_start();
        echo 'partial-output';
        ob_start();
        echo 'view-output';

        $debug->onUncaughtException(new Exception(uniqid('var-'), 1234));
        /** @var string $actual */
        $actual = ob_get_clean();

        $remaining = ob_get_level() - $level;
        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        $this->assertSame(0, $remaining);
        $this->assertStringStartsWith('<!DOCTYPE html>', $actual);
        $this->assertStringNotContainsString('layout-output', $actual);
        $this->assertStringNotContainsString('partial-output', $actual);
    }

    public function testSupportDebugOnUncaughtExceptionResetsIsActiveWhenRenderingFails(): void
    {
        $renderer = new class () extends HtmlRenderer {
            public function render(ExceptionReport $report): string
            {
                throw new RuntimeException('render failed');
            }
        };

        $isActive = new ReflectionProperty(Debug::class, 'isActive');
        $isActive->setValue(null, false);

        ob_start();
        $debug = new Debug();
        $debug->setRenderer($renderer);

        ob_start();
        try {
            $debug->onUncaughtException(new Exception(uniqid('var-'), 1234));
            $this->fail('The render error must reach the caller.');
        } catch (RuntimeException $error) {
            $this->assertSame('render failed', $error->getMessage());
        } finally {
            ob_end_clean();
        }

        $activeAfter = (bool) $isActive->getValue();
        $isActive->setValue(null, false);

        $this->assertFalse($activeAfter);
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

        /**
         * The handler discards the buffers that start after the component is
         * created and echoes the rendered output, so create it inside the
         * buffer that the test reads.
         */
        ob_start();
        $debug = new Debug();
        $debug->setRenderer($renderer);
        $exception = new Exception(uniqid('var-'), 1234);

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
        $exception = new Exception($message, 1234);

        $isActive = new ReflectionProperty(Debug::class, 'isActive');
        $isActive->setValue(null, true);

        ob_start();
        $debug = new Debug();
        ob_start();
        $result = $debug->onUncaughtException($exception);
        /** @var string $actual */
        $actual = ob_get_clean();

        $isActive->setValue(null, false);

        $this->assertFalse($result);
        $this->assertStringContainsString($message, $actual);
    }

    public function testSupportDebugOnUncaughtExceptionWhenAlreadyActiveEscapesTheMessage(): void
    {
        $isActive = new ReflectionProperty(Debug::class, 'isActive');
        $isActive->setValue(null, true);

        ob_start();
        $debug = new Debug();
        ob_start();
        $debug->onUncaughtException(new Exception('<img src=x onerror=alert(1)>', 1234));
        /** @var string $actual */
        $actual = ob_get_clean();

        $isActive->setValue(null, false);

        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $actual);
    }
}
