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
use Phalcon\DebugBar\Contracts\Binder;
use Phalcon\Di\DiInterface;

/**
 * Binds the debug bar's services onto the classic `Phalcon\Di\Di` container.
 */
final class Di implements Binder
{
    public function __construct(
        private readonly DiInterface $container
    ) {
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return $this->container->has($name);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function resolve(string $name): mixed
    {
        return $this->container->get($name);
    }

    /**
     * @param string  $name
     * @param Closure $factory
     *
     * @return void
     */
    public function set(string $name, Closure $factory): void
    {
        $this->container->setShared($name, $factory);
    }
}
