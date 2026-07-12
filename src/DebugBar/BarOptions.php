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

/**
 * Immutable output options for the debug bar: where the assets load from, the
 * CSP nonce stamped on the injected tags, and whether the diagnostic headers
 * are set. A single bag so new primitives can be added without widening every
 * constructor that carries them.
 */
final class BarOptions
{
    /**
     * @param string      $assetUri
     * @param bool        $headers
     * @param string|null $nonce
     */
    public function __construct(
        public readonly string $assetUri,
        public readonly bool $headers,
        public readonly ?string $nonce
    ) {
    }
}
