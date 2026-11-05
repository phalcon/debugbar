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

use DateTimeImmutable;
use DateTimeZone;
use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\RequestContext;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;

use function is_float;
use function sprintf;

/**
 * Holds the request history workflow: it finds the endpoint request, stores the
 * collected payload and queues the browser cookie. `Core::createHistory()` creates
 * it, and the routes of each integration read its storage and its endpoint.
 *
 * @phpstan-import-type payload from DebugBarTypes
 */
final class HistoryRecorder
{
    public function __construct(
        private readonly History $history,
        private readonly HistoryEndpoint $endpoint,
        private readonly HistoryCookie $cookie,
        private readonly Redactor $redactor
    ) {
    }

    public function endpoint(): HistoryEndpoint
    {
        return $this->endpoint;
    }

    public function history(): History
    {
        return $this->history;
    }

    public function isEndpointRequest(): bool
    {
        return $this->endpoint->matches();
    }

    /**
     * @param payload $payload
     */
    public function record(
        array $payload,
        ResponseInterface $response,
        ?RequestInterface $request,
        RequestContext $context
    ): void {
        if (null !== $request) {
            $this->history->save(
                $payload,
                new RequestMetadata(
                    $request->getMethod(),
                    $this->redactor->redactUri($request->getURI()),
                    $response->getStatusCode() ?? 200,
                    $context->isAjax,
                    $this->requestedAt()
                )
            );
        }

        $this->cookie->queue($this->endpoint->cookiePath(), $context->isSecure);
    }

    private function requestedAt(): ?DateTimeImmutable
    {
        $timestamp = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        if (!is_float($timestamp)) {
            return null;
        }

        $requestedAt = DateTimeImmutable::createFromFormat(
            'U.u',
            sprintf('%.6F', $timestamp),
            new DateTimeZone('UTC')
        );

        return false === $requestedAt ? null : $requestedAt;
    }
}
