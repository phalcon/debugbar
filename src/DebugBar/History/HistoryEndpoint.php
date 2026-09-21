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

use Phalcon\Di\DiInterface;
use Phalcon\Events\EventInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Mvc\DispatcherInterface;
use Phalcon\Mvc\Url\UrlInterface;

use function is_string;
use function ltrim;
use function parse_url;
use function rtrim;

use const PHP_URL_PATH;

/**
 * Selects the package controller without adding or changing application routes.
 * The public URL includes the application's base URI.
 */
final class HistoryEndpoint
{
    public const CONTROLLER_NAMESPACE = 'Phalcon\\DebugBar\\Controllers';

    public function __construct(
        private readonly HistoryOptions $options,
        private readonly DiInterface $container
    ) {
    }

    public function __invoke(EventInterface $event, mixed $source, mixed $dispatcher): void
    {
        if (!$dispatcher instanceof DispatcherInterface || !$this->matches()) {
            return;
        }

        $dispatcher->setNamespaceName(self::CONTROLLER_NAMESPACE);
        $dispatcher->setControllerName('history');
        $dispatcher->setControllerSuffix('Controller');
        $dispatcher->setActionSuffix('Action');
        $dispatcher->setActionName('open');
        $dispatcher->setParams([]);
    }

    public function cookiePath(): string
    {
        $baseUri = '/';
        if ($this->container->has('url')) {
            $url = $this->container->getShared('url');
            if ($url instanceof UrlInterface) {
                $path = parse_url($url->getBaseUri(), PHP_URL_PATH);
                if (is_string($path) && '' !== $path) {
                    $baseUri = $path;
                }
            }
        }

        return '/' . ltrim(rtrim($baseUri, '/') . '/', '/');
    }

    public function matches(): bool
    {
        $request = $this->request();

        return null !== $request && parse_url($request->getURI(), PHP_URL_PATH) === $this->url();
    }

    public function url(): string
    {
        return rtrim($this->cookiePath(), '/') . '/' . ltrim($this->options->url, '/');
    }

    private function request(): ?RequestInterface
    {
        if (!$this->container->has('request')) {
            return null;
        }

        $request = $this->container->getShared('request');

        return $request instanceof RequestInterface ? $request : null;
    }
}
