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

use Closure;
use Phalcon\Http\RequestInterface;

use function is_string;
use function ltrim;
use function parse_url;
use function rtrim;

use const PHP_URL_PATH;

/**
 * Finds the public URL of the history endpoint, which includes the base URI of
 * the application. It has no container: each integration gives closures that read
 * the request and the base URI, and the endpoint calls them when it needs a value.
 */
final class HistoryEndpoint
{
    public const CONTROLLER_NAMESPACE = 'Phalcon\\DebugBar\\Controllers';

    /**
     * @param Closure(): ?RequestInterface $request
     * @param Closure(): string            $baseUri
     */
    public function __construct(
        private readonly HistoryOptions $options,
        private readonly Closure $request,
        private readonly Closure $baseUri
    ) {
    }

    public function cookiePath(): string
    {
        $path    = parse_url(($this->baseUri)(), PHP_URL_PATH);
        $baseUri = is_string($path) && '' !== $path ? $path : '/';

        return '/' . ltrim(rtrim($baseUri, '/') . '/', '/');
    }

    public function matches(): bool
    {
        $request = ($this->request)();

        return null !== $request && parse_url($request->getURI(), PHP_URL_PATH) === $this->url();
    }

    public function url(): string
    {
        return rtrim($this->cookiePath(), '/') . '/' . ltrim($this->options->url, '/');
    }
}
