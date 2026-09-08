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
    public function move(string $source, string $target): bool;

    public function read(string $file): false | string;

    public function remove(string $file): bool;

    public function removeDirectory(string $directory): bool;

    public function write(string $file, string $contents): bool;
}
