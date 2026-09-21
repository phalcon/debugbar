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
use PHPUnit\Framework\Attributes\DataProvider;

final class HistoryOptionsTest extends AbstractUnitTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function invalidHistoryUrls(): array
    {
        return [
            'relative' => ['_debugbar/open'],
            'scheme'   => ['https://example.com/_debugbar/open'],
            'host'     => ['//example.com/_debugbar/open'],
            'query'    => ['/_debugbar/open?request=1'],
            'fragment' => ['/_debugbar/open#history'],
        ];
    }

    public function testDisabledHistoryAllowsAnEmptyStoragePath(): void
    {
        $options = new HistoryOptions();
        $options->validate();

        $this->assertSame('', $options->path);
    }

    public function testEnabledHistoryAcceptsAbsoluteStoragePaths(): void
    {
        $unix = new HistoryOptions(true, '/_debugbar/open', '/var/debugbar/');
        $unix->validate();
        $this->assertSame('/var/debugbar', $unix->path);

        $windows = new HistoryOptions(true, '/_debugbar/open', 'C:\\var\\debugbar\\');
        $windows->validate();
        $this->assertSame('C:\\var\\debugbar', $windows->path);

        $unc = new HistoryOptions(true, '/_debugbar/open', '\\\\server\\debugbar\\');
        $unc->validate();
        $this->assertSame('\\\\server\\debugbar', $unc->path);

        $root = new HistoryOptions(true, '/_debugbar/open', '/');
        $root->validate();
        $this->assertSame('/', $root->path);
    }

    public function testEnabledHistoryRequiresAnAbsoluteStoragePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('history.path must be an absolute path');

        (new HistoryOptions(true, '/_debugbar/open', 'var/debugbar'))->validate();
    }

    public function testEnabledHistoryRequiresAnExplicitStoragePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('history.path is required');

        (new HistoryOptions(true))->validate();
    }

    #[DataProvider('invalidHistoryUrls')]
    public function testEnabledHistoryRequiresAnInternalPathUrl(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('history.url must be an absolute path');

        (new HistoryOptions(true, $url, '/var/debugbar'))->validate();
    }
}
