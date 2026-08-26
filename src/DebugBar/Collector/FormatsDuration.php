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
 * Formats a duration as a rounded millisecond label (e.g. `12.34ms`). Shared by
 * the collectors that time their own work: `msLabel()` for a millisecond source
 * (wall clock), `nanosToMs()` for an `hrtime()` nanosecond delta.
 */
trait FormatsDuration
{
    /**
     * @param float|int $milliseconds
     *
     * @return string
     */
    private function msLabel(float | int $milliseconds): string
    {
        return round($milliseconds, 2) . 'ms';
    }

    /**
     * @param float|int $nanoseconds
     *
     * @return string
     */
    private function nanosToMs(float | int $nanoseconds): string
    {
        return $this->msLabel($nanoseconds / 1e6);
    }
}
