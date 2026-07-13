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

use ErrorException;
use Phalcon\Debug;
use Phalcon\Debug\Exceptions\RuntimeWarning;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class OnUncaughtLowSeverityTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2020-09-09
     */
    public function testSupportDebugOnUncaughtLowSeverity(): void
    {
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Test warning message');
        $this->expectExceptionCode(0);

        $debug = new Debug();

        /**
         * PHPUnit lowers error_reporting() to unhandleable levels while a test
         * runs, which masks out E_WARNING. Add it back so the handler guard
         * (error_reporting() & severity) is satisfied, then restore the level.
         */
        $previous = error_reporting(error_reporting() | E_WARNING);

        try {
            $debug->onUncaughtLowSeverity(
                E_WARNING,
                'Test warning message',
                __FILE__,
                __LINE__
            );
        } finally {
            error_reporting($previous);
        }
    }

    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2026-07-13
     */
    public function testSupportDebugOnUncaughtLowSeverityMaskedSeverity(): void
    {
        $debug  = new Debug();
        $thrown = false;

        /**
         * When the severity is not part of the current error_reporting() mask
         * the handler must stay silent. This proves the guard uses a bitwise
         * AND (not OR) and that the branch is actually taken.
         */
        $previous = error_reporting(0);

        try {
            $debug->onUncaughtLowSeverity(
                E_WARNING,
                'Masked warning message',
                __FILE__,
                __LINE__
            );
        } catch (RuntimeWarning) {
            $thrown = true;
        } finally {
            error_reporting($previous);
        }

        $this->assertFalse($thrown);
    }
}
