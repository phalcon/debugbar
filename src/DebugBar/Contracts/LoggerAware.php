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
 * Implemented by a collector that accepts application log entries, so the
 * DebugBar `addLog()` method (and the logger adapter that drives it) can
 * delegate to it.
 */
interface LoggerAware
{
    /**
     * @param string                  $message
     * @param string                  $level
     * @param array<array-key, mixed> $context
     *
     * @return void
     */
    public function addLog(string $message, string $level, array $context = []): void;
}
