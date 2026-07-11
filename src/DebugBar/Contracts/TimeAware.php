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

namespace Phalcon\DebugBar\Contracts;

/**
 * Implemented by a collector that measures durations, so the DebugBar
 * `startMeasure()`/`stopMeasure()` convenience methods can delegate to it.
 */
interface TimeAware
{
    /**
     * @param string      $name
     * @param string|null $label
     *
     * @return void
     */
    public function startMeasure(string $name, ?string $label = null): void;

    /**
     * @param string $name
     *
     * @return void
     */
    public function stopMeasure(string $name): void;
}
