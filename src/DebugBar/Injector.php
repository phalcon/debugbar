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

use Phalcon\Http\ResponseInterface;

use function is_string;
use function mb_strtolower;
use function str_contains;
use function stripos;
use function strlen;
use function strripos;
use function substr;

/**
 * Splices the rendered bar into an HTML response. Framework-agnostic: it only
 * touches the response's content and headers, so any hook (MVC event, manual
 * call) can drive it.
 */
final class Injector
{
    /**
     * Inserts the head assets and bar body just before the last `</body>`.
     * Content with no `</body>` does not change. A `Content-Length` header gets
     * the new length.
     */
    public function inject(ResponseInterface $response, string $head, string $body): ResponseInterface
    {
        $content  = $response->getContent();
        $position = strripos($content, '</body>');

        if (false === $position) {
            return $response;
        }

        $content = substr($content, 0, $position)
            . $head . $body
            . substr($content, $position);

        $response->setContent($content);
        if (is_string($response->getHeaders()->get('Content-Length'))) {
            $response->setContentLength(strlen($content));
        }

        return $response;
    }

    /**
     * Whether the response is an HTML page. A response with no content type counts
     * as HTML.
     */
    public function isHtml(ResponseInterface $response): bool
    {
        $contentType = $response->getHeaders()->get('Content-Type');
        if (!is_string($contentType)) {
            return true;
        }

        return str_contains(mb_strtolower($contentType), 'text/html');
    }

    /**
     * Whether the bar may be injected: an HTML page with a `</body>` tag, not a
     * redirect, not AJAX, not compressed. A file download, an empty response,
     * JSON and an HTML fragment have no `</body>`.
     */
    public function shouldInject(ResponseInterface $response, bool $isAjax = false): bool
    {
        if (true === $isAjax || is_string($response->getHeaders()->get('Content-Encoding'))) {
            return false;
        }

        $status = $response->getStatusCode();
        if (null !== $status && $status >= 300 && $status < 400) {
            return false;
        }

        return $this->isHtml($response)
            && false !== stripos($response->getContent(), '</body>');
    }
}
