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

use Phalcon\Events\EventInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Mvc\DispatcherInterface;

use function parse_url;

use const PHP_URL_PATH;

/**
 * Selects the package controller without adding or changing application routes.
 * The public URL includes the application's base URI.
 */
final class HistoryEndpoint
{
    public function __construct(
        public readonly string $url,
        private readonly RequestInterface $request
    ) {
    }

    public function __invoke(EventInterface $event, mixed $source, mixed $dispatcher): void
    {
        if (!$dispatcher instanceof DispatcherInterface || !$this->matches()) {
            return;
        }

        $dispatcher->setNamespaceName('Phalcon\\DebugBar\\Controllers');
        $dispatcher->setControllerName('history');
        $dispatcher->setControllerSuffix('Controller');
        $dispatcher->setActionSuffix('Action');
        $dispatcher->setActionName('open');
        $dispatcher->setParams([]);
    }

    public function matches(): bool
    {
        return parse_url($this->request->getURI(), PHP_URL_PATH) === $this->url;
    }
}
