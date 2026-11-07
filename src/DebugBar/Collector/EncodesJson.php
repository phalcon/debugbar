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

/**
 * Encodes a value as JSON with `Json::encode()`, returning an empty string when
 * the encoding fails. Shared by the classes that render structured values into
 * a panel.
 */
trait EncodesJson
{
    private function jsonOrEmpty(mixed $value): string
    {
        $json = Json::encode($value);

        return is_string($json) ? $json : '';
    }
}
