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

use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\Provider;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Controller;
use RuntimeException;

use function is_string;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * Internal MVC adapter for /_debugbar/open. GET returns the current session's
 * request list or one stored entry; DELETE clears that session's history.
 */
final class HistoryController extends Controller
{
    /**
     * @return ResponseInterface
     */
    public function clearAction(): ResponseInterface
    {
        return $this->handle(
            'DELETE',
            fn (
                RequestInterface $request,
                ResponseInterface $response,
                FilesystemHistory $history
            ): ResponseInterface => $this->json($response, ['cleared' => $history->clear()])
        );
    }

    /**
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        return $this->handle(
            'GET',
            function (
                RequestInterface $request,
                ResponseInterface $response,
                FilesystemHistory $history
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

    /**
     * @param callable(RequestInterface, ResponseInterface, FilesystemHistory): ResponseInterface $action
     */
    private function handle(string $expectedMethod, callable $action): ResponseInterface
    {
        $container = $this->getDI() ?? throw new RuntimeException('The History controller requires a DI container.');
        $request   = $container->getShared('request');
        $response  = $container->getShared('response');
        $history   = $container->getShared(Provider::HISTORY_SERVICE);
        $access    = $container->getShared(Provider::ACCESS_GATE_SERVICE);

        if (!$response instanceof ResponseInterface) {
            throw new RuntimeException('The response service must implement ResponseInterface.');
        }

        if (
            !$request instanceof RequestInterface
            || !$history instanceof FilesystemHistory
            || !$access instanceof AccessGate
        ) {
            return $this->json($response, ['error' => 'History is unavailable.'], 500);
        }

        $clientIp = $request->getClientAddress();
        if (!$access->allows(is_string($clientIp) ? $clientIp : null)) {
            return $this->json($response, ['error' => 'Not found.'], 404);
        }

        if ($expectedMethod !== $request->getMethod()) {
            return $this->json($response, ['error' => 'Method not allowed.'], 405);
        }

        return $action($request, $response, $history);
    }

    /**
     * @param ResponseInterface     $response
     * @param array<string, mixed> $body
     * @param int                  $status
     *
     * @return ResponseInterface
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
