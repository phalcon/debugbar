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

namespace Phalcon\DebugBar\Collector;

use Phalcon\DebugBar\Json;

use function is_string;

use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Encodes a value as JSON with `Json::encode()`, returning an empty string when
 * the encoding fails. Shared by the classes that render structured values into
 * a panel, so each panel shows `/` and Unicode the same way. A value that
 * cannot be encoded, such as `INF`, becomes `0` and the other values stay.
 */
trait EncodesJson
{
    private function jsonOrEmpty(mixed $value, int $flags = 0): string
    {
        $json = Json::encode(
            $value,
            $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return is_string($json) ? $json : '';
    }
}
