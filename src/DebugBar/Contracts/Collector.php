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
 * The data contract every collector implements. `collect()` returns an
 * envelope: `panel` holds the data shaped for the declared panel renderer,
 * `badge` is the optional scalar tab count.
 */
interface Collector
{
    /**
     * Runs the collector and returns its envelope.
     *
     * @return array{panel: mixed, badge: scalar|null}
     */
    public function collect(): array;

    /**
     * The unique key for this collector (its NAME constant).
     *
     * @return string
     */
    public function getName(): string;
}
