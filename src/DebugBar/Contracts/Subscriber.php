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
 * Implemented by a collector that gathers its data from framework events. At
 * boot the provider hands each such collector the application's EventsManager
 * so it can attach its own listeners.
 */
interface Subscriber
{
    /**
     * @param ManagerInterface $eventsManager
     *
     * @return void
     */
    public function subscribe(ManagerInterface $eventsManager): void;
}
