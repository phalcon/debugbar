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

namespace Phalcon\DebugBar\Exceptions;

use InvalidArgumentException;
use Phalcon\DebugBar\Contracts\DebugBarThrowable;

/**
 * Thrown when a configuration value of the bar is not valid, for example a
 * `history` option.
 */
class InvalidConfig extends InvalidArgumentException implements DebugBarThrowable
{
}
