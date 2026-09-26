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

use function str_ends_with;
use function time;

/**
 * Test-only stream wrapper for exercising storage failures through the public
 * FilesystemHistory API.
 */
final class FailingStreamWrapper
{
    /**
     * @var resource|null
     */
    public $context;

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
    }

    /**
     * @return array<int|string, int>
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function url_stat(string $path, int $flags): array
    {
        return $this->stat(str_ends_with($path, '.json') ? 0100666 : 0040777);
    }

    /**
     * @return array<int|string, int>
     */
    private function stat(int $mode): array
    {
        $now = time();

        return [
            0, 0, $mode, 1, 0, 0, 0, 0, $now, $now, $now, -1, -1,
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => $mode,
            'nlink'   => 1,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => $now,
            'mtime'   => $now,
            'ctime'   => $now,
            'blksize' => -1,
            'blocks'  => -1,
        ];
    }
}
