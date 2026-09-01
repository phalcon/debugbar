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

/**
 * The small request snapshot stored next to a collected debug-bar payload.
 */
final class RequestMetadata
{
    /**
     * @param string $method
     * @param string $uri
     * @param int    $status
     * @param bool   $ajax
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly int $status,
        public readonly bool $ajax
    ) {
    }
}
