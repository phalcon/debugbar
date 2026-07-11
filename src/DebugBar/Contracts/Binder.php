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

namespace Phalcon\DebugBar\Contracts;

use Closure;

/**
 * The container-neutral seam the registration core binds through, so it never
 * needs to know whether it is running on a `Phalcon\Di\Di` or a
 * `Phalcon\Container\Container`.
 */
interface Binder
{
    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function resolve(string $name): mixed;

    /**
     * @param string  $name
     * @param Closure $factory
     *
     * @return void
     */
    public function set(string $name, Closure $factory): void;
}
