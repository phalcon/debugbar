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
 * A minimal grid collector used to exercise the collector contracts.
 */
final class GridCollector extends AbstractCollector
{
    public const NAME = 'grid_fixture';

    /**
     * @var string
     */
    protected string $icon = 'icon-grid';

    /**
     * @var string
     */
    protected string $label = 'Grid';

    /**
     * @var string
     */
    protected string $panel = 'grid';

    /**
     * @return array{panel: array<string, scalar>, badge: scalar|null}
     */
    public function collect(): array
    {
        return [
            'panel' => [
                'key'   => 'value',
                'count' => 2,
            ],
            'badge' => 2,
        ];
    }
}
