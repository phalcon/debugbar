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
 * Catch-all marker implemented by every exception this package throws, so
 * integrators can catch `DebugBarThrowable` to trap anything from the bar.
 */
interface DebugBarThrowable extends Throwable
{
}
