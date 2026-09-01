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
final class OpenHandlerController extends Controller
{
    /**
     * @return ResponseInterface
     */
    public function clearAction(): ResponseInterface
    {
        $container = $this->getDI();
        if (null === $container) {
            throw new RuntimeException('The OpenHandler controller requires a DI container.');
        }

        $request  = $container->getShared('request');
        $response = $container->getShared('response');
        $history  = $container->getShared(Provider::HISTORY_SERVICE);
        $access   = $container->getShared(Provider::ACCESS_GATE_SERVICE);

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

        if ('DELETE' !== $request->getMethod()) {
            return $this->json($response, ['error' => 'Method not allowed.'], 405);
        }

        return $this->json($response, ['cleared' => $history->clear()]);
    }
    /**
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        $container = $this->getDI();
        if (null === $container) {
            throw new RuntimeException('The OpenHandler controller requires a DI container.');
        }

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

        if ('GET' !== $request->getMethod()) {
            return $this->json($response, ['error' => 'Method not allowed.'], 405);
        }

        $id = $request->getQuery('id');
        if (!is_string($id) || '' === $id) {
            return $this->json($response, ['requests' => $history->find()]);
        }

        $entry = $history->get($id);
        if (null === $entry) {
            return $this->json($response, ['error' => 'Request not found.'], 404);
        }

        return $this->json($response, ['request' => $entry]);
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
