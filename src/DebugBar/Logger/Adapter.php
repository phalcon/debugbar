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

use Phalcon\DebugBar\DebugBar;
use Phalcon\Logger\Adapter\AbstractAdapter;
use Phalcon\Logger\Item;

/**
 * A `Phalcon\Logger` adapter that forwards every logged item to the debug bar's
 * `LoggerCollector`. Attach it to the application logger and its output is
 * captured in the bar's "Logs" tab:
 *
 * ```php
 * $logger->addAdapter('debugbar', new Adapter(Debug::getBar()));
 * ```
 *
 * The bar is nullable because `Debug::getBar()` returns `null` until the bar
 * boots, so the adapter can be attached unconditionally. Forwarding goes
 * through `DebugBar::addLog()`, so it also no-ops when the logger collector is
 * disabled. Only `process()` is overridden; the inherited `commit()` replays
 * queued items through it, covering transactional logging.
 */
final class Adapter extends AbstractAdapter
{
    /**
     * @param DebugBar|null $bar
     */
    public function __construct(private readonly ?DebugBar $bar = null)
    {
    }

    /**
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @param Item $item
     *
     * @return void
     */
    public function process(Item $item): void
    {
        $this->bar?->addLog($item->getMessage(), $item->getLevelName(), $item->getContext());
    }
}
