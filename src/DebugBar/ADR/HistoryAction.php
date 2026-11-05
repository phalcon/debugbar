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

namespace Phalcon\DebugBar\ADR;

use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\History\HistoryHandler;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

/**
 * Serves the history endpoint in an ADR application. It creates its own response,
 * so that it does not change a response of the application.
 */
final class HistoryAction implements Action
{
    public function __construct(
        private readonly History $history,
        private readonly AccessGate $accessGate
    ) {
    }

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        return (new HistoryHandler($this->history, $this->accessGate, $request, new Response()))->open();
    }
}
