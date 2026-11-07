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

namespace Phalcon\DebugBar\Mvc;

use Phalcon\DebugBar\Controllers\HistoryController;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\Events\EventInterface;
use Phalcon\Mvc\DispatcherInterface;

/**
 * Selects the package controller without adding or changing application routes.
 * `Provider` attaches it to `application:beforeHandleRequest`.
 */
final class HistoryListener
{
    public const CONTROLLER_NAMESPACE = 'Phalcon\\DebugBar\\Controllers';

    public function __construct(private readonly HistoryEndpoint $endpoint)
    {
    }

    public function __invoke(EventInterface $event, mixed $source, mixed $dispatcher): void
    {
        if (!$dispatcher instanceof DispatcherInterface || !$this->endpoint->matches()) {
            return;
        }

        $dispatcher->setNamespaceName(self::CONTROLLER_NAMESPACE);
        $dispatcher->setControllerName(HistoryController::NAME);
        $dispatcher->setControllerSuffix('Controller');
        $dispatcher->setActionSuffix('Action');
        $dispatcher->setActionName(HistoryController::ACTION);
        $dispatcher->setParams([]);
    }
}
