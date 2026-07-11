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

namespace Phalcon\DebugBar\Security;

use function in_array;

/**
 * Decides whether the current request may see the bar, even within an allowed
 * environment: an IP allowlist (empty = any) and/or a custom callback. Both, when
 * given, must pass.
 */
final class AccessGate
{
    /**
     * @var list<string>
     */
    private array $allowedIps;

    /**
     * @var (callable(): bool)|null
     */
    private $callback;

    /**
     * @param list<string>            $allowedIps empty allows any client
     * @param (callable(): bool)|null $callback
     */
    public function __construct(array $allowedIps = [], ?callable $callback = null)
    {
        $this->allowedIps = $allowedIps;
        $this->callback   = $callback;
    }

    /**
     * @param string|null $clientIp
     *
     * @return bool
     */
    public function allows(?string $clientIp): bool
    {
        if (
            [] !== $this->allowedIps
            && (null === $clientIp || !in_array($clientIp, $this->allowedIps, true))
        ) {
            return false;
        }

        if (null !== $this->callback) {
            return ($this->callback)();
        }

        return true;
    }
}
