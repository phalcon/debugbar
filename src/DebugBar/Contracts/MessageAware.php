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
 * Implemented by a collector that accepts messages, so the DebugBar
 * `message()`/`info()`/… convenience methods can delegate to it.
 */
interface MessageAware
{
    /**
     * @param mixed  $message
     * @param string $label
     *
     * @return void
     */
    public function addMessage(mixed $message, string $label): void;
}
