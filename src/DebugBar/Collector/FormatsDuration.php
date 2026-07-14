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

namespace Phalcon\DebugBar\Collector;

use function round;

/**
 * Formats an `hrtime()` nanosecond delta as a rounded millisecond label (e.g.
 * `12.34ms`). Shared by the collectors that time their own work.
 */
trait FormatsDuration
{
    /**
     * @param int|float $nanoseconds
     *
     * @return string
     */
    private function nanosToMs(int|float $nanoseconds): string
    {
        return round($nanoseconds / 1e6, 2) . 'ms';
    }
}
