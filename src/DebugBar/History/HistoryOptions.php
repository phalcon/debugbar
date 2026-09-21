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

use InvalidArgumentException;

use function max;
use function parse_url;
use function preg_match;
use function rtrim;
use function str_starts_with;
use function trim;

use const PHP_URL_FRAGMENT;
use const PHP_URL_HOST;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;
use const PHP_URL_SCHEME;

/**
 * Immutable request-history configuration shared by the provider and storage.
 */
final class HistoryOptions
{
    public readonly int $maxRequests;

    public readonly string $path;

    public readonly int $ttlSeconds;

    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $url = '/_debugbar/open',
        string $path = '',
        int $maxRequests = 100,
        int $ttlSeconds = 86400
    ) {
        $path              = trim($path);
        $this->path        = $this->trimTrailingSeparators($path);
        $this->maxRequests = max(1, $maxRequests);
        $this->ttlSeconds  = max(1, $ttlSeconds);
    }

    public function validate(): void
    {
        if (!$this->enabled) {
            return;
        }

        if ('' === $this->path) {
            throw new InvalidArgumentException('history.path is required when request history is enabled.');
        }

        if (!$this->isAbsolutePath($this->path)) {
            throw new InvalidArgumentException('history.path must be an absolute path.');
        }

        if (!$this->isInternalUrl($this->url)) {
            throw new InvalidArgumentException(
                'history.url must be an absolute path without a scheme, host, query, or fragment.'
            );
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || 1 === preg_match('/^[a-zA-Z]:[\\\\\/]/D', $path);
    }

    private function isInternalUrl(string $url): bool
    {
        return str_starts_with($url, '/')
            && $url === parse_url($url, PHP_URL_PATH)
            && null === parse_url($url, PHP_URL_SCHEME)
            && null === parse_url($url, PHP_URL_HOST)
            && null === parse_url($url, PHP_URL_QUERY)
            && null === parse_url($url, PHP_URL_FRAGMENT);
    }

    private function trimTrailingSeparators(string $path): string
    {
        if ('/' === $path || 1 === preg_match('/^[a-zA-Z]:[\\\\\/]$/D', $path)) {
            return $path;
        }

        return rtrim($path, '/\\');
    }
}
