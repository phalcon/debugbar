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

use Phalcon\DebugBar\Collector\AbstractCollector;

/**
 * Declares the `grid` panel but returns a non-conforming (non-array) payload,
 * so the panel contract assertion is proven to reject drift.
 */
final class MalformedGridCollector extends AbstractCollector
{
    public const NAME = 'malformed_grid_fixture';

    /**
     * @var string
     */
    protected string $panel = 'grid';

    /**
     * @return array{panel: string, badge: scalar|null}
     */
    public function collect(): array
    {
        return [
            'panel' => 'not-a-grid',
            'badge' => null,
        ];
    }
}
