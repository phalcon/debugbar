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

namespace Phalcon\Tests\Support\Support\Dump;

/**
 * Class ClassProperties
 */
class ClassProperties
{
    public int $foo = 1;

    protected int $bar = 2;

    // @phpstan-ignore property.onlyWritten (read via reflection by the Dump component under test)
    private int $baz = 3;
}
