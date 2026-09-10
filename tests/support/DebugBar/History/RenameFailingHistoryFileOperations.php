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

use Phalcon\DebugBar\History\HistoryFileOperations;

use function rmdir;

final class RenameFailingHistoryFileOperations implements HistoryFileOperations
{
    public int $removeCalls = 0;

    public function move(string $source, string $target): bool
    {
        return false;
    }

    public function read(string $file): false | string
    {
        return false;
    }

    public function remove(string $file): bool
    {
        $this->removeCalls++;

        return true;
    }

    public function removeDirectory(string $directory): bool
    {
        return @rmdir($directory);
    }

    public function write(string $file, string $contents): bool
    {
        return true;
    }
}
