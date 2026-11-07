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

namespace Phalcon\DebugBar;

use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;

/**
 * The JSON encoder of the bar. It always adds `JSON_INVALID_UTF8_SUBSTITUTE`, so
 * a byte that is not valid UTF-8 becomes U+FFFD and the encoding does not fail.
 * Each caller gives its own flags and handles a failure in its own way.
 */
final class Json
{
    public static function encode(mixed $value, int $flags = 0): false | string
    {
        return json_encode($value, $flags | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
