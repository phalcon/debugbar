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

namespace Phalcon\DebugBar\Controllers;

use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\History\HistoryHandler;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\ControllerInterface;

/**
 * Internal MVC adapter for /_debugbar/open. `HistoryHandler` does the work.
 */
final class HistoryController implements ControllerInterface
{
    private HistoryHandler $handler;

    public function __construct(
        History $history,
        AccessGate $accessGate,
        RequestInterface $historyRequest,
        ResponseInterface $historyResponse
    ) {
        $this->handler = new HistoryHandler($history, $accessGate, $historyRequest, $historyResponse);
    }

    public function clearAction(): ResponseInterface
    {
        return $this->handler->clear();
    }

    public function indexAction(): ResponseInterface
    {
        return $this->handler->index();
    }

    public function openAction(): ResponseInterface
    {
        return $this->handler->open();
    }
}
