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

use Exception as BaseException;
use Phalcon\DebugBar\Contracts\DebugBarThrowable;

/**
 * Base exception for the debug bar package.
 */
class Exception extends BaseException implements DebugBarThrowable
{
}
