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
use function preg_match;
use function rtrim;
use function str_starts_with;
use function trim;

/**
 * Immutable request-history configuration shared by the provider, response
 * listener, collector, and controller.
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
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\\\')
            || 1 === preg_match('/^[a-zA-Z]:[\\\\\/]/D', $path);
    }

    private function trimTrailingSeparators(string $path): string
    {
        if ('/' === $path || 1 === preg_match('/^[a-zA-Z]:[\\\\\/]$/D', $path)) {
            return $path;
        }

        return rtrim($path, '/\\');
    }
}
