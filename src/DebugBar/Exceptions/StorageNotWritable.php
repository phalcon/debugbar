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

use Phalcon\DebugBar\Contracts\DebugBarThrowable;
use RuntimeException;

/**
 * Thrown when the web-server user cannot create or write to the history
 * storage directory.
 */
class StorageNotWritable extends RuntimeException implements DebugBarThrowable
{
}
