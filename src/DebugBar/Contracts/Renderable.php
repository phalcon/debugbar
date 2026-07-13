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

namespace Phalcon\DebugBar\Contracts;

use Phalcon\DebugBar\DebugBarTypes;

/**
 * A collector that also describes its tab/panel. The `panel` value selects the
 * client renderer ('grid' | 'list' | 'code' | 'html'); the tab count comes from
 * `collect()['badge']`, not from here.
 *
 * @phpstan-import-type widget from DebugBarTypes
 */
interface Renderable extends Collector
{
    /**
     * Describes the tab and panel for this collector.
     *
     * @return widget
     */
    public function getWidget(): array;
}
