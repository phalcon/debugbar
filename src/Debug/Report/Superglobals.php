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

namespace Phalcon\Debug\Report;

/**
 * Immutable snapshot of the request superglobals. fromGlobals() is the single
 * named boundary where $_REQUEST/$_SERVER are read in the Phalcon\Debug
 * namespace, keeping the renderer free of direct superglobal access and usable
 * in CLI / early-boot / no-DI contexts.
 */
final class Superglobals
{
    /**
     * @param array<array-key, mixed> $request
     * @param array<array-key, mixed> $server
     */
    public function __construct(
        private readonly array $request,
        private readonly array $server,
    ) {
    }

    /**
     * Builds a snapshot from the live request superglobals.
     *
     * @return self
     */
    public static function fromGlobals(): self
    {
        return new self($_REQUEST, $_SERVER);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getServer(): array
    {
        return $this->server;
    }
}
