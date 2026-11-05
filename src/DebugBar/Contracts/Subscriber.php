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

use Phalcon\Events\ManagerInterface;

/**
 * A custom collector implements this to attach its own listeners. `DebugBar::addCollector()`
 * calls `subscribe()` when the bar has an events manager. The built-in collectors
 * do not use it: the bridges feed them.
 */
interface Subscriber
{
    public function subscribe(ManagerInterface $eventsManager): void;
}
