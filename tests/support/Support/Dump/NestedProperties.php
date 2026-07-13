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
 * Fixture whose only property holds a nested array. Used to exercise the
 * detailed (reflection) property branch of Dump together with the recursive
 * indentation of a nested value.
 */
class NestedProperties
{
    /**
     * @var array<string, string>
     */
    // @phpstan-ignore property.onlyWritten (read via reflection by the Dump component under test)
    private array $data = ['k' => 'v'];
}
