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

namespace Phalcon\DebugBar;

use Phalcon\DebugBar\History\HistoryRecorder;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Events\EventInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;

use function count;

/**
 * The response listener: MVC attaches it to `application:beforeSendResponse` and
 * ADR to `application:afterHandle`. On the event it runs the access gate,
 * aggregates the bar, sets the diagnostic headers, and - for an injectable HTML
 * response - renders and splices the bar in. `handle()` does the same work with
 * no event.
 */
final class ResponseListener
{
    public function __construct(
        private readonly DebugBar $bar,
        private readonly Renderer $renderer,
        private readonly Injector $injector,
        private readonly AccessGate $accessGate,
        private readonly ?RequestInterface $request,
        private readonly BarOptions $options,
        private readonly ?HistoryRecorder $history = null
    ) {
    }

    public function __invoke(EventInterface $event, mixed $source, mixed $response): void
    {
        if (!$response instanceof ResponseInterface) {
            return;
        }

        $this->handle($response);
    }

    public function handle(ResponseInterface $response): void
    {
        if ($this->history?->isEndpointRequest()) {
            return;
        }

        $context = RequestContext::fromRequest($this->request);
        if (true !== $this->accessGate->allows($context->clientIp)) {
            return;
        }

        $collected = $this->bar->collect();

        $this->history?->record($collected, $response, $this->request, $context);

        if (true === $this->options->headers) {
            $response->setHeader('X-Debug-Bar', (string) count($collected['data']));
        }

        if (true === $this->injector->shouldInject($response, $context->isAjax)) {
            $this->injector->inject(
                $response,
                $this->renderer->renderHead($this->options->nonce),
                $this->renderer->render($collected, $this->options->nonce)
            );
        }
    }
}
