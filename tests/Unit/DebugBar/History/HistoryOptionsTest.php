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

namespace Phalcon\Tests\Unit\DebugBar\History;

use InvalidArgumentException;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class HistoryOptionsTest extends AbstractUnitTestCase
{
    public function testDisabledHistoryAllowsAnEmptyStoragePath(): void
    {
        $options = new HistoryOptions();

        $this->assertSame('', $options->path);
    }

    public function testEnabledHistoryRequiresAnExplicitStoragePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('history.path is required');

        new HistoryOptions(true);
    }
}
