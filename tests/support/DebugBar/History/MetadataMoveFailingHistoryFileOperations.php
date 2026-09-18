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

final class MetadataMoveFailingHistoryFileOperations extends DelegatingHistoryFileOperations
{
    public function move(string $source, string $target): bool
    {
        return !str_ends_with($target, '.json.meta') && $this->delegate->move($source, $target);
    }
}
