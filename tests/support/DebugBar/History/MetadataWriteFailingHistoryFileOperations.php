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

use function str_contains;

final class MetadataWriteFailingHistoryFileOperations extends DelegatingHistoryFileOperations
{
    public function write(string $file, string $contents): bool
    {
        if (str_contains($file, '.json.meta.tmp-')) {
            $this->delegate->write($file, $contents);

            return false;
        }

        return $this->delegate->write($file, $contents);
    }
}
