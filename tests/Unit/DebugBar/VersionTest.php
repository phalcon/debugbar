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

namespace Phalcon\Tests\Unit\DebugBar;

use Phalcon\DebugBar\Version;
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function testGetReturnsASemverString(): void
    {
        $version = new Version();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $version->get());
    }
}
