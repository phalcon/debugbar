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

use function is_string;
use function json_encode;

/**
 * Encodes a value as JSON, returning an empty string when `json_encode()`
 * fails (it returns `false`). Shared by the collectors that render structured
 * values into a panel.
 */
trait EncodesJson
{
    /**
     * @param mixed $value
     *
     * @return string
     */
    private function jsonOrEmpty(mixed $value): string
    {
        $json = json_encode($value);

        return is_string($json) ? $json : '';
    }
}
