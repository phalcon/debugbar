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

    public function getFirstLine(): int
    {
        return $this->firstLine;
    }

    public function getLastLine(): int
    {
        return $this->lastLine;
    }

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

    public function getMode(): string
    {
        return $this->mode;
    }
}
