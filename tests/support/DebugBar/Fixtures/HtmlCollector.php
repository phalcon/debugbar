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
 * A minimal html collector used to exercise the `html` panel contract.
 */
final class HtmlCollector extends AbstractCollector
{
    public const NAME = 'html_fixture';

    /**
     * @var string
     */
    protected string $panel = 'html';

    /**
     * @return array{panel: string, badge: scalar|null}
     */
    public function collect(): array
    {
        return [
            'panel' => '<p>safe</p>',
            'badge' => null,
        ];
    }
}
