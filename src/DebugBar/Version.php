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
 * Exposes the debug bar package version.
 */
final class Version
{
    private const VERSION = '0.1.0';

    public function get(): string
    {
        return self::VERSION;
    }
}
