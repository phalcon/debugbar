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
     * Inserts the head assets and bar body just before the last `</body>`
     * (or appends them when there is no body tag).
     *
     * @param ResponseInterface $response
     * @param string            $head
     * @param string            $body
     *
     * @return ResponseInterface
     */
    public function inject(ResponseInterface $response, string $head, string $body): ResponseInterface
    {
        $content  = $response->getContent();
        $position = strripos($content, '</body>');

        if (false === $position) {
            $content .= $head . $body;
        } else {
            $content = substr($content, 0, $position)
                . $head . $body
                . substr($content, $position);
        }

        $response->setContent($content);

        return $response;
    }

    /**
     * Whether the bar may be injected: HTML content, not a redirect, not AJAX.
     *
     * @param ResponseInterface $response
     * @param bool              $isAjax
     *
     * @return bool
     */
    public function shouldInject(ResponseInterface $response, bool $isAjax = false): bool
    {
        if (true === $isAjax) {
            return false;
        }

        $status = $response->getStatusCode();
        if (null !== $status && $status >= 300 && $status < 400) {
            return false;
        }

        $contentType = $response->getHeaders()->get('Content-Type');
        if (!is_string($contentType)) {
            return true;
        }

        return str_contains(mb_strtolower($contentType), 'text/html');
    }
}
