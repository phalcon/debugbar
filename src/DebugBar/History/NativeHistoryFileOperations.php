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

use Closure;
use ErrorException;

use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function glob;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function restore_error_handler;
use function rmdir;
use function set_error_handler;
use function unlink;

use const LOCK_EX;

final class NativeHistoryFileOperations implements HistoryFileOperations
{
    public function createDirectory(string $directory, int $permissions, bool $recursive): bool
    {
        return $this->attempt(
            static fn (): bool => mkdir($directory, $permissions, $recursive),
            false
        );
    }

    public function directoryExists(string $directory): bool
    {
        return is_dir($directory);
    }

    public function fileExists(string $file): bool
    {
        return is_file($file);
    }

    public function matching(string $pattern, int $flags = 0): array
    {
        return $this->attempt(static fn (): array | false => glob($pattern, $flags), false) ?: [];
    }

    public function modifiedAt(string $file): false | int
    {
        return $this->attempt(static fn (): false | int => filemtime($file), false);
    }

    public function move(string $source, string $target): bool
    {
        return $this->attempt(static fn (): bool => rename($source, $target), false);
    }

    public function read(string $file): false | string
    {
        return $this->attempt(static fn (): false | string => file_get_contents($file), false);
    }

    public function remove(string $file): bool
    {
        return $this->attempt(static fn (): bool => unlink($file), false);
    }

    public function removeDirectory(string $directory): bool
    {
        return $this->attempt(static fn (): bool => rmdir($directory), false);
    }

    public function write(string $file, string $contents): bool
    {
        return false !== $this->attempt(
            static fn (): false | int => file_put_contents($file, $contents, LOCK_EX),
            false
        );
    }

    /**
     * Converts a native filesystem warning into the method's documented failure value.
     *
     * @template T
     *
     * @param Closure(): T $operation
     * @param T            $fallback
     *
     * @return T
     */
    private function attempt(Closure $operation, mixed $fallback): mixed
    {
        set_error_handler(
            static function (int $severity, string $message): never {
                throw new ErrorException($message, 0, $severity);
            }
        );

        try {
            return $operation();
        } catch (ErrorException) {
            return $fallback;
        } finally {
            restore_error_handler();
        }
    }
}
