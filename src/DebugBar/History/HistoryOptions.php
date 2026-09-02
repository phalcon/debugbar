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

use function max;
use function rtrim;
use function sys_get_temp_dir;

/**
 * Immutable request-history configuration shared by the provider, response
 * listener, collector, and controller.
 */
final class HistoryOptions
{
    /**
     * @var int
     */
    public readonly int $maxRequests;

    /**
     * @var string
     */
    public readonly string $path;

    /**
     * @var int
     */
    public readonly int $ttlSeconds;

    /**
     * @param bool   $enabled
     * @param string $url
     * @param string $path
     * @param int    $maxRequests
     * @param int    $ttlSeconds
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $url = '/_debugbar/open',
        string $path = '',
        int $maxRequests = 100,
        int $ttlSeconds = 86400
    ) {
        $this->path        = rtrim('' !== $path ? $path : sys_get_temp_dir() . '/phalcon-debugbar', '/\\');
        $this->maxRequests = max(1, $maxRequests);
        $this->ttlSeconds  = max(1, $ttlSeconds);
    }
}
