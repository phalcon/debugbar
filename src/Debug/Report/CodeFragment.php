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

namespace Phalcon\Debug\Report;

/**
 * Immutable value object describing the source-code fragment shown for a
 * backtrace frame (the window of lines around the offending line).
 */
final class CodeFragment
{
    /**
     * @param string             $mode
     * @param int                $firstLine
     * @param int                $line
     * @param int                $lastLine
     * @param array<int, string> $lines
     */
    public function __construct(
        private readonly string $mode,
        private readonly int $firstLine,
        private readonly int $line,
        private readonly int $lastLine,
        private readonly array $lines,
    ) {
    }

    /**
     * @return int
     */
    public function getFirstLine(): int
    {
        return $this->firstLine;
    }

    /**
     * @return int
     */
    public function getLastLine(): int
    {
        return $this->lastLine;
    }

    /**
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @return array<int, string>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    /**
     * @return string
     */
    public function getMode(): string
    {
        return $this->mode;
    }
}
