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
 * A minimal list collector used to exercise the `list` panel contract.
 */
final class ListCollector extends AbstractCollector
{
    public const NAME = 'list_fixture';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @return array{panel: list<array{message: string, label?: string}>, badge: scalar|null}
     */
    public function collect(): array
    {
        return [
            'panel' => [
                ['message' => 'first'],
                ['message' => 'second', 'label' => 'info'],
            ],
            'badge' => 2,
        ];
    }
}
