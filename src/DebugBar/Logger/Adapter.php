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

namespace Phalcon\DebugBar\Logger;

use Phalcon\DebugBar\Debug;
use Phalcon\Logger\Adapter\AbstractAdapter;
use Phalcon\Logger\Item;

/**
 * A `Phalcon\Logger` adapter that forwards every logged item to the debug bar's
 * `LoggerCollector`. Attach it to the application logger and its output is
 * captured in the bar's "Logs" tab:
 *
 * ```php
 * $logger->addAdapter('debugbar', new Adapter());
 * ```
 *
 * The adapter reads the active bar each time the logger writes, so you can create
 * it before the bar boots. Items that the logger writes before the boot are lost.
 * Forwarding goes through `DebugBar::addLog()`, so it also no-ops when the logger
 * collector is disabled. Only `process()` is overridden; the inherited `commit()`
 * replays queued items through it, covering transactional logging.
 */
final class Adapter extends AbstractAdapter
{
    public function close(): bool
    {
        return true;
    }

    public function process(Item $item): void
    {
        Debug::getBar()?->addLog($item->getMessage(), $item->getLevelName(), $item->getContext());
    }
}
