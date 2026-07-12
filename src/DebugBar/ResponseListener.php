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
 * gate, aggregates the bar, sets the diagnostic headers, and — for an injectable
 * HTML response — renders and splices the bar in.
 */
final class ResponseListener
{
    /**
     * @param DebugBar              $bar
     * @param Renderer              $renderer
     * @param Injector              $injector
     * @param AccessGate            $accessGate
     * @param RequestInterface|null $request
     * @param string                $assetUri
     * @param string|null           $nonce
     * @param bool                  $headers
     */
    public function __construct(
        private readonly DebugBar $bar,
        private readonly Renderer $renderer,
        private readonly Injector $injector,
        private readonly AccessGate $accessGate,
        private readonly ?RequestInterface $request,
        private readonly string $assetUri,
        private readonly ?string $nonce,
        private readonly bool $headers
    ) {
    }

    /**
     * @param EventInterface $event
     * @param mixed          $source
     * @param mixed          $response
     *
     * @return void
     */
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

        if (true === $this->headers) {
            $response->setHeader('X-Debug-Bar', (string) count($collected['data']));
        }

        if (true === $this->injector->shouldInject($response, $isAjax)) {
            $this->injector->inject(
                $response,
                $this->renderer->renderHead($this->assetUri, $this->nonce),
                $this->renderer->render($collected, $this->nonce)
            );
        }
    }

    /**
     * @return array{0: string|null, 1: bool}
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
