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

final class GarbageCollectionMarkerFailingFileOperations extends DelegatingHistoryFileOperations
{
    public function write(string $file, string $contents): bool
    {
        return !str_ends_with($file, '/.gc')
            && $this->delegate->write($file, $contents);
    }
}
