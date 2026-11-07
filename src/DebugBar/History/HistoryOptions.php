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

use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\Exceptions\InvalidConfig;

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
 * Immutable request-history configuration shared by `Core` and storage.
 *
 * @phpstan-import-type history_config from DebugBarTypes
 */
final class HistoryOptions
{
    public readonly int $maxBrowsers;

    public readonly int $maxRequests;

    public readonly string $path;

    public readonly int $ttlSeconds;

    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $url = '/_debugbar/open',
        string $path = '',
        int $maxRequests = 100,
        int $ttlSeconds = 86400,
        int $maxBrowsers = 10,
        public readonly string $baseUri = '/'
    ) {
        $path              = trim($path);
        $this->path        = $this->trimTrailingSeparators($path);
        $this->maxRequests = max(1, $maxRequests);
        $this->ttlSeconds  = max(1, $ttlSeconds);
        $this->maxBrowsers = max(1, $maxBrowsers);
    }

    /**
     * @param history_config $config
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            $config['enabled'] ?? false,
            $config['url'] ?? '/_debugbar/open',
            $config['path'] ?? '',
            $config['max_requests'] ?? 100,
            $config['ttl_seconds'] ?? 86400,
            $config['max_browsers'] ?? 10,
            $config['base_uri'] ?? '/'
        );
    }

    public function validate(): void
    {
        if (!$this->enabled) {
            return;
        }

        if ('' === $this->path) {
            throw new InvalidConfig('history.path is required when request history is enabled.');
        }

        if (!$this->isAbsolutePath($this->path)) {
            throw new InvalidConfig('history.path must be an absolute path.');
        }

        if (!$this->isInternalUrl($this->url)) {
            throw new InvalidConfig(
                'history.url must be an absolute path without a scheme, host, query, or fragment.'
            );
        }

        if ('/' === $this->url) {
            throw new InvalidConfig(
                'history.url must not be "/". The endpoint would replace the home page of the application.'
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
