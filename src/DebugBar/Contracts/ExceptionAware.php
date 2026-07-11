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

use Throwable;

/**
 * Implemented by a collector that records throwables, so the DebugBar
 * `addException()` convenience method can delegate to it.
 */
interface ExceptionAware
{
    /**
     * @param Throwable $throwable
     *
     * @return void
     */
    public function addThrowable(Throwable $throwable): void;
}
