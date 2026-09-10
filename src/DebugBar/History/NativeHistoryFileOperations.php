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

use function file_get_contents;
use function file_put_contents;
use function rename;
use function rmdir;
use function unlink;

use const LOCK_EX;

final class NativeHistoryFileOperations implements HistoryFileOperations
{
    public function move(string $source, string $target): bool
    {
        return @rename($source, $target);
    }

    public function read(string $file): false | string
    {
        return @file_get_contents($file);
    }

    public function remove(string $file): bool
    {
        return @unlink($file);
    }

    public function removeDirectory(string $directory): bool
    {
        return @rmdir($directory);
    }

    public function write(string $file, string $contents): bool
    {
        return false !== @file_put_contents($file, $contents, LOCK_EX);
    }
}
