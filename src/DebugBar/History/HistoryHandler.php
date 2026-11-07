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

namespace Phalcon\DebugBar\History;

use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\Json;
use Phalcon\DebugBar\RequestContext;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;

use function is_string;
use function strtoupper;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Serves the history endpoint with no framework application. GET returns the
 * request list of the current browser or one stored entry. DELETE clears the
 * history of that browser.
 */
final class HistoryHandler
{
    public function __construct(
        private readonly History $history,
        private readonly AccessGate $accessGate,
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response
    ) {
    }

    public function clear(): ResponseInterface
    {
        return $this->handle(
            'DELETE',
            fn (): ResponseInterface => $this->json(['cleared' => $this->history->clear()])
        );
    }

    public function index(): ResponseInterface
    {
        return $this->handle(
            'GET',
            function (): ResponseInterface {
                $id = $this->request->getQuery('id');
                if (null === $id) {
                    return $this->json(['requests' => $this->history->find()]);
                }
                if (!is_string($id)) {
                    return $this->json(['error' => 'Request not found.'], 404);
                }

                $entry = $this->history->get($id);
                if (null === $entry) {
                    return $this->json(['error' => 'Request not found.'], 404);
                }

                return $this->json(['request' => $entry]);
            }
        );
    }

    public function open(): ResponseInterface
    {
        return 'DELETE' === $this->transportMethod() ? $this->clear() : $this->index();
    }

    /**
     * @param callable(): ResponseInterface $action
     */
    private function handle(string $expectedMethod, callable $action): ResponseInterface
    {
        if (!$this->accessGate->allows(RequestContext::fromRequest($this->request)->clientIp)) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        if ($expectedMethod !== $this->transportMethod()) {
            return $this->json(['error' => 'Method not allowed.'], 405);
        }

        return $action();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body, int $status = 200): ResponseInterface
    {
        $json = Json::encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->response->setStatusCode($status);
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setHeader('Cache-Control', 'no-store, private');
        $this->response->setHeader('X-Content-Type-Options', 'nosniff');
        $this->response->setContent(false === $json ? '{}' : $json);

        return $this->response;
    }

    private function transportMethod(): string
    {
        return strtoupper($this->request->getServer('REQUEST_METHOD') ?? '');
    }
}
