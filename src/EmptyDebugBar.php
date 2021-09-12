<?php

/**
 * This file is part of the Phalcon.
 *
 * (c) Phalcon Team <team@phalcon.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phalcon\DebugBar;

class EmptyDebugBar
{
    public function __call($name, $arguments)
    {
    }

    public static function __callStatic($name, $arguments)
    {
    }
}
