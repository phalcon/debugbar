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

namespace Phalcon\Debug\Renderer;

use function count;
use function get_class;
use function gettype;
use function htmlentities;
use function implode;
use function is_array;
use function is_bool;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function method_exists;
use function str_replace;

use const ENT_COMPAT;

/**
 * Produces the compact, single-line HTML representation of a value used inside
 * the backtrace signatures and the request/server/variables grids. Extracted
 * from HtmlRenderer so presentation assembly and value formatting change for
 * separate reasons.
 */
class ValueDumper
{
    /**
     * Formats a single value as a compact one-line string.
     *
     * @param mixed $variable
     *
     * @return string
     */
    public function dump(mixed $variable): string
    {
        return match (true) {
            is_bool($variable)   => $this->dumpBool($variable),
            is_string($variable) => $this->dumpString($variable),
            is_scalar($variable) => $this->dumpNumber($variable),
            is_object($variable) => $this->dumpObject($variable),
            is_array($variable)  => 'Array(' . $this->dumpArray($variable) . ')',
            null === $variable   => $this->dumpNull(),
            default              => gettype($variable),
        };
    }

    /**
     * Formats an array as a compact one-line string, guarded to three levels of
     * nesting and collapsing arrays of ten or more entries to their count.
     *
     * @param array<array-key, mixed> $arguments
     * @param int                     $number
     *
     * @return string|null
     */
    public function dumpArray(array $arguments, int $number = 0): string | null
    {
        if ($number >= 3 || empty($arguments)) {
            return null;
        }

        if (count($arguments) >= 10) {
            return (string) count($arguments);
        }

        $dump = [];
        foreach ($arguments as $index => $argument) {
            if ('' === $argument) {
                $varDump = '(empty string)';
            } elseif (is_scalar($argument)) {
                $varDump = $this->escape((string) $argument);
            } elseif (is_array($argument)) {
                $varDump = 'Array(' . $this->dumpArray($argument, $number + 1) . ')';
            } elseif (is_object($argument)) {
                $varDump = 'Object(' . get_class($argument) . ')';
            } elseif (null === $argument) {
                $varDump = 'null';
            } elseif (is_resource($argument)) {
                $varDump = (string) $argument;
            } else {
                $varDump = gettype($argument);
            }

            $dump[] = '[' . $index . '] =&gt; ' . $varDump;
        }

        return implode(', ', $dump);
    }

    /**
     * Escapes a string for safe inclusion in the HTML output, rendering literal
     * newlines as the two-character "\n" sequence.
     *
     * @param string $value
     *
     * @return string
     */
    public function escape(string $value): string
    {
        return htmlentities(
            str_replace("\n", "\\n", $value),
            ENT_COMPAT,
            'utf-8'
        );
    }

    /**
     * @param bool $variable
     *
     * @return string
     */
    private function dumpBool(bool $variable): string
    {
        return $variable ? 'true' : 'false';
    }

    /**
     * @return string
     */
    private function dumpNull(): string
    {
        return 'null';
    }

    /**
     * @param float|int $variable
     *
     * @return string
     */
    private function dumpNumber(float | int $variable): string
    {
        return (string) $variable;
    }

    /**
     * @param object $variable
     *
     * @return string
     */
    private function dumpObject(object $variable): string
    {
        $className = get_class($variable);

        if (true === method_exists($variable, 'dump')) {
            $dumpedObject = $variable->dump();

            return 'Object(' . $className . ': ' . $this->dumpArray((array) $dumpedObject) . ')';
        }

        return 'Object(' . $className . ')';
    }

    /**
     * @param string $variable
     *
     * @return string
     */
    private function dumpString(string $variable): string
    {
        return $this->escape($variable);
    }
}
