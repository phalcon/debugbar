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
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\ControllerInterface;

use function is_string;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Internal MVC adapter for /_debugbar/open. GET returns the current session's
 * request list or one stored entry; DELETE clears that session's history.
 */
final class HistoryController implements ControllerInterface
{
    public function __construct(
        private readonly History $history,
        private readonly AccessGate $accessGate,
        private readonly RequestInterface $historyRequest,
        private readonly ResponseInterface $historyResponse
    ) {
    }

    public function clearAction(): ResponseInterface
    {
        return $this->handle(
            'DELETE',
            fn (
                RequestInterface $request,
                ResponseInterface $response,
                History $history
            ): ResponseInterface => $this->json($response, ['cleared' => $history->clear()])
        );
    }

    public function indexAction(): ResponseInterface
    {
        return $this->handle(
            'GET',
            function (
                RequestInterface $request,
                ResponseInterface $response,
                History $history
            ): ResponseInterface {
                $id = $request->getQuery('id');
                if (null === $id) {
                    return $this->json($response, ['requests' => $history->find()]);
                }
                if (!is_string($id)) {
                    return $this->json($response, ['error' => 'Request not found.'], 404);
                }

                $entry = $history->get($id);
                if (null === $entry) {
                    return $this->json($response, ['error' => 'Request not found.'], 404);
                }

                return $this->json($response, ['request' => $entry]);
            }
        );
    }

    public function openAction(): ResponseInterface
    {
        return 'DELETE' === $this->historyRequest->getMethod() ? $this->clearAction() : $this->indexAction();
    }

    /**
     * @param callable(RequestInterface, ResponseInterface, History): ResponseInterface $action
     */
    private function handle(string $expectedMethod, callable $action): ResponseInterface
    {
        $request    = $this->historyRequest;
        $response   = $this->historyResponse;
        $history    = $this->history;
        $accessGate = $this->accessGate;

        $clientIp = $request->getClientAddress();
        if (!$accessGate->allows(is_string($clientIp) ? $clientIp : null)) {
            return $this->json($response, ['error' => 'Not found.'], 404);
        }

        if ($expectedMethod !== $request->getMethod()) {
            return $this->json($response, ['error' => 'Method not allowed.'], 405);
        }

        return $action($request, $response, $history);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(ResponseInterface $response, array $body, int $status = 200): ResponseInterface
    {
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response->setStatusCode($status);
        $response->setContentType('application/json', 'UTF-8');
        $response->setHeader('Cache-Control', 'no-store, private');
        $response->setContent(false === $json ? '{}' : $json);

        return $response;
    }
}
