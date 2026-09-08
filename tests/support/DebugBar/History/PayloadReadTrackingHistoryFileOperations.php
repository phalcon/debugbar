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
use Phalcon\DebugBar\History\NativeHistoryFileOperations;

use function str_ends_with;

final class PayloadReadTrackingHistoryFileOperations implements HistoryFileOperations
{
    public int $payloadReads = 0;

    private readonly NativeHistoryFileOperations $native;

    public function __construct()
    {
        $this->native = new NativeHistoryFileOperations();
    }

    public function move(string $source, string $target): bool
    {
        return $this->native->move($source, $target);
    }

    public function read(string $file): false | string
    {
        if (str_ends_with($file, '.json')) {
            $this->payloadReads++;
        }

        return $this->native->read($file);
    }

    public function remove(string $file): bool
    {
        return $this->native->remove($file);
    }

    public function removeDirectory(string $directory): bool
    {
        return $this->native->removeDirectory($directory);
    }

    public function write(string $file, string $contents): bool
    {
        return $this->native->write($file, $contents);
    }
}
