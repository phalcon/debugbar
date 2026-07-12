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

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

/**
 * Flattens a nested (already-redacted) array into the flat `array<string, scalar>`
 * a grid panel expects, joining nested keys with dots and stringifying leaves.
 * Shared by the snapshot collectors that render a grid.
 */
trait FlattensToGrid
{
    /**
     * @param array<array-key, mixed> $data
     * @param string                  $prefix
     *
     * @return array<string, scalar>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $name = ('' === $prefix) ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                foreach ($this->flatten($value, $name) as $nestedKey => $nestedValue) {
                    $result[$nestedKey] = $nestedValue;
                }

                continue;
            }

            $result[$name] = $this->stringify($value);
        }

        return $result;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }
}
