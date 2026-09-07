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

use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Events\EventInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;

use function count;
use function is_string;

/**
 * The `application:beforeSendResponse` listener. On the event it runs the access
 * gate, aggregates the bar, sets the diagnostic headers, and - for an injectable
 * HTML response - renders and splices the bar in.
 *
 * @phpstan-import-type request_context from DebugBarTypes
 */
final class ResponseListener
{
    public function __construct(
        private readonly DebugBar $bar,
        private readonly Renderer $renderer,
        private readonly Injector $injector,
        private readonly AccessGate $accessGate,
        private readonly ?RequestInterface $request,
        private readonly BarOptions $options
    ) {
    }

    public function __invoke(EventInterface $event, mixed $source, mixed $response): void
    {
        if (!$response instanceof ResponseInterface) {
            return;
        }

        [$clientIp, $isAjax] = $this->requestContext();
        if (true !== $this->accessGate->allows($clientIp)) {
            return;
        }

        $collected = $this->bar->collect();

        if (true === $this->options->headers) {
            $response->setHeader('X-Debug-Bar', (string) count($collected['data']));
        }

        if (true === $this->injector->shouldInject($response, $isAjax)) {
            $this->injector->inject(
                $response,
                $this->renderer->renderHead($this->options->nonce),
                $this->renderer->render($collected, $this->options->nonce)
            );
        }
    }

    /**
     * @return request_context
     */
    private function requestContext(): array
    {
        if (null === $this->request) {
            return [null, false];
        }

        $clientIp = $this->request->getClientAddress();

        return [is_string($clientIp) ? $clientIp : null, $this->request->isAjax()];
    }
}
