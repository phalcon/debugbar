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

use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\History\RequestMetadata;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Events\EventInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;

use function count;
use function is_string;
use function parse_url;

use const PHP_URL_PATH;

/**
 * The `application:beforeSendResponse` listener. On the event it runs the access
 * gate, aggregates the bar, sets the diagnostic headers, and - for an injectable
 * HTML response - renders and splices the bar in.
 *
 * @phpstan-import-type request_context from DebugBarTypes
 * @phpstan-import-type payload from DebugBarTypes
 */
final class ResponseListener
{
    /**
     * @param DebugBar              $bar
     * @param Renderer              $renderer
     * @param Injector              $injector
     * @param AccessGate            $accessGate
     * @param RequestInterface|null $request
     * @param BarOptions            $options
     */
    public function __construct(
        private readonly DebugBar $bar,
        private readonly Renderer $renderer,
        private readonly Injector $injector,
        private readonly AccessGate $accessGate,
        private readonly ?RequestInterface $request,
        private readonly BarOptions $options,
        private readonly ?FilesystemHistory $history = null,
        private readonly ?HistoryOptions $historyOptions = null
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

        $this->record($collected, $response, $isAjax);

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
     * @param payload              $collected
     * @param ResponseInterface   $response
     * @param bool                $isAjax
     *
     * @return void
     */
    private function record(array $collected, ResponseInterface $response, bool $isAjax): void
    {
        if (null === $this->history || null === $this->historyOptions || null === $this->request) {
            return;
        }

        $uri  = $this->request->getURI();
        $path = parse_url($uri, PHP_URL_PATH);
        if (is_string($path) && $path === $this->historyOptions->url) {
            return;
        }

        $this->history->save(
            $collected,
            new RequestMetadata(
                $this->request->getMethod(),
                $uri,
                $response->getStatusCode() ?? 200,
                $isAjax
            )
        );
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
