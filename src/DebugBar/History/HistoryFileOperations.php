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

namespace Phalcon\DebugBar\History;

/**
 * Internal filesystem access seam used by request-history storage.
 *
 * @internal
 */
interface HistoryFileOperations
{
    public function createDirectory(string $directory, int $permissions, bool $recursive): bool;

    /**
     * @phpstan-impure
     */
    public function directoryExists(string $directory): bool;

    public function fileExists(string $file): bool;

    public function isWritable(string $path): bool;

    /**
     * @return list<string>
     */
    public function matching(string $pattern, int $flags = 0): array;

    public function modifiedAt(string $file): false | int;

    public function move(string $source, string $target): bool;

    public function read(string $file): false | string;

    public function remove(string $file): bool;

    public function removeDirectory(string $directory): bool;

    public function write(string $file, string $contents): bool;
}
