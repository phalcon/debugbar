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
use function strtoupper;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Internal MVC adapter for /_debugbar/open. GET returns the current browser's
 * request list or one stored entry; DELETE clears that browser's history.
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
            fn (): ResponseInterface => $this->json(
                $this->historyResponse,
                ['cleared' => $this->history->clear()]
            )
        );
    }

    public function indexAction(): ResponseInterface
    {
        return $this->handle(
            'GET',
            function (): ResponseInterface {
                $id = $this->historyRequest->getQuery('id');
                if (null === $id) {
                    return $this->json($this->historyResponse, ['requests' => $this->history->find()]);
                }
                if (!is_string($id)) {
                    return $this->json($this->historyResponse, ['error' => 'Request not found.'], 404);
                }

                $entry = $this->history->get($id);
                if (null === $entry) {
                    return $this->json($this->historyResponse, ['error' => 'Request not found.'], 404);
                }

                return $this->json($this->historyResponse, ['request' => $entry]);
            }
        );
    }

    public function openAction(): ResponseInterface
    {
        return 'DELETE' === $this->transportMethod() ? $this->clearAction() : $this->indexAction();
    }

    /**
     * @param callable(): ResponseInterface $action
     */
    private function handle(string $expectedMethod, callable $action): ResponseInterface
    {
        $clientIp = $this->historyRequest->getClientAddress();
        if (!$this->accessGate->allows(is_string($clientIp) ? $clientIp : null)) {
            return $this->json($this->historyResponse, ['error' => 'Not found.'], 404);
        }

        if ($expectedMethod !== $this->transportMethod()) {
            return $this->json($this->historyResponse, ['error' => 'Method not allowed.'], 405);
        }

        return $action();
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
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setContent(false === $json ? '{}' : $json);

        return $response;
    }

    private function transportMethod(): string
    {
        return strtoupper($this->historyRequest->getServer('REQUEST_METHOD') ?? '');
    }
}
