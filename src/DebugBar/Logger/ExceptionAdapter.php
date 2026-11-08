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

use Phalcon\DebugBar\Collector\ExceptionsCollector;
use Phalcon\DebugBar\Contracts\ExceptionAware;
use Phalcon\DebugBar\Debug;
use Phalcon\Logger\Adapter\AbstractAdapter;
use Phalcon\Logger\Enum;
use Phalcon\Logger\Item;
use Throwable;

/**
 * A `Phalcon\Logger` adapter that gives the throwable in `context['exception']`
 * to the debug bar's `ExceptionsCollector`. `Phalcon\ADR\ErrorResponder` logs each
 * exception in this form and fires no event. Keep this class separate from
 * `Adapter`: an MVC application that logs an exception would record it two times.
 * The adapter reads the active bar each time the logger writes, so you can create
 * it before the bar boots.
 *
 * Only an item at error level or higher sets the last exception, which the ADR
 * debug page shows. An item at a lower level is an exception that the
 * application handled: it shows only in the Exceptions panel.
 */
final class ExceptionAdapter extends AbstractAdapter
{
    public function close(): bool
    {
        return true;
    }

    public function process(Item $item): void
    {
        $exception = $item->getContext()['exception'] ?? null;
        $bar       = Debug::getBar();
        if (!$exception instanceof Throwable || null === $bar) {
            return;
        }

        if ($item->getLevel() <= Enum::ERROR) {
            $bar->addException($exception);

            return;
        }

        $collector = $bar->hasCollector(ExceptionsCollector::NAME)
            ? $bar->getCollector(ExceptionsCollector::NAME)
            : null;
        if ($collector instanceof ExceptionAware) {
            $collector->addThrowable($exception);
        }
    }
}
