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

namespace Phalcon\DebugBar\ADR;

use Phalcon\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Injector;
use Phalcon\DebugBar\ResponseListener;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Events\EventInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Throwable;

use function is_string;

/**
 * Shows the debug page for a server error in an ADR application. The application
 * catches each exception before `Debug::listen()` can see it, so this class reads
 * the exception that the logger gave to the bar. It runs the bar listener first,
 * so that the debug page replaces a page with the bar in it. The event fires
 * outside the `try` block of the application: a failure in the debug page keeps
 * the error page of the application.
 */
final class DebugPageListener
{
    public function __construct(
        private readonly ResponseListener $listener,
        private readonly DebugBar $bar,
        private readonly Debug $debug,
        private readonly AccessGate $accessGate,
        private readonly ?RequestInterface $request
    ) {
    }

    public function __invoke(EventInterface $event, mixed $source, mixed $response): void
    {
        ($this->listener)($event, $source, $response);

        $exception = $this->bar->getLastException();
        if (null === $exception || !$response instanceof ResponseInterface || !$this->applies($response)) {
            return;
        }

        try {
            $response->setContent($this->debug->renderHtml($exception));
        } catch (Throwable) {
            return;
        }
    }

    /**
     * A server error on an HTML page, for a client that the access gate allows.
     */
    private function applies(ResponseInterface $response): bool
    {
        $clientIp = $this->request?->getClientAddress();

        return ($response->getStatusCode() ?? 0) >= 500
            && $this->accessGate->allows(is_string($clientIp) ? $clientIp : null)
            && (new Injector())->shouldInject($response, $this->request?->isAjax() ?? false);
    }
}
