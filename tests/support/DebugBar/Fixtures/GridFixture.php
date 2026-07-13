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

namespace Phalcon\Tests\Support\DebugBar\Fixtures;

use Phalcon\DebugBar\Collector\FlattensToGrid;

/**
 * Exposes the private `FlattensToGrid::flatten()` so the trait's grid
 * flattening can be asserted directly, independent of any collector.
 */
final class GridFixture
{
    use FlattensToGrid;

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<string, scalar>
     */
    public function toGrid(array $data): array
    {
        return $this->flatten($data);
    }
}
