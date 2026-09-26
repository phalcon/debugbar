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

use Closure;
use Phalcon\DebugBar\History\HistoryFileOperations;
use Phalcon\DebugBar\History\NativeHistoryFileOperations;

abstract class DelegatingHistoryFileOperations implements HistoryFileOperations
{
    protected readonly HistoryFileOperations $delegate;

    public function __construct(?HistoryFileOperations $delegate = null)
    {
        $this->delegate = $delegate ?? new NativeHistoryFileOperations();
    }

    public function createDirectory(string $directory, int $permissions, bool $recursive): bool
    {
        return $this->delegate->createDirectory($directory, $permissions, $recursive);
    }

    public function directoryExists(string $directory): bool
    {
        return $this->delegate->directoryExists($directory);
    }

    public function fileExists(string $file): bool
    {
        return $this->delegate->fileExists($file);
    }

    public function isWritable(string $path): bool
    {
        return $this->delegate->isWritable($path);
    }

    public function matching(string $pattern, int $flags = 0): array
    {
        return $this->delegate->matching($pattern, $flags);
    }

    public function modifiedAt(string $file): false | int
    {
        return $this->delegate->modifiedAt($file);
    }

    public function move(string $source, string $target): bool
    {
        return $this->delegate->move($source, $target);
    }

    public function read(string $file): false | string
    {
        return $this->delegate->read($file);
    }

    public function remove(string $file): bool
    {
        return $this->delegate->remove($file);
    }

    public function removeDirectory(string $directory): bool
    {
        return $this->delegate->removeDirectory($directory);
    }

    public function withExclusiveLock(string $file, Closure $operation): bool
    {
        return $this->delegate->withExclusiveLock($file, $operation);
    }

    public function write(string $file, string $contents): bool
    {
        return $this->delegate->write($file, $contents);
    }
}
