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

namespace Phalcon\Tests\Support\DebugBar\History;

final class RecordingMatchingHistoryFileOperations extends DelegatingHistoryFileOperations
{
    /**
     * @var list<string>
     */
    public array $directoryChecks = [];

    /**
     * @var list<string>
     */
    public array $patterns = [];

    public function directoryExists(string $directory): bool
    {
        $this->directoryChecks[] = $directory;

        return true;
    }

    public function isWritable(string $path): bool
    {
        return true;
    }

    public function matching(string $pattern, int $flags = 0): array
    {
        $this->patterns[] = $pattern;

        return [];
    }
}
