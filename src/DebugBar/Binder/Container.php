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

namespace Phalcon\DebugBar\Binder;

use Closure;
use Phalcon\Contracts\Container\Service\Collection;
use Phalcon\DebugBar\Contracts\Binder;

/**
 * Binds the debug bar's services onto the newer `Phalcon\Container\Container`
 * (received as its service `Collection`).
 */
final class Container implements Binder
{
    public function __construct(
        private readonly Collection $services
    ) {
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return $this->services->has($name);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function resolve(string $name): mixed
    {
        return $this->services->get($name);
    }

    /**
     * @param string  $name
     * @param Closure $factory
     *
     * @return void
     */
    public function set(string $name, Closure $factory): void
    {
        $this->services->set($name, $factory);
    }
}
