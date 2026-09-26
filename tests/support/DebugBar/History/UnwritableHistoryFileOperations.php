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

final class UnwritableHistoryFileOperations extends DelegatingHistoryFileOperations
{
    public bool $writable = true;

    public function isWritable(string $path): bool
    {
        return $this->writable && parent::isWritable($path);
    }
}
