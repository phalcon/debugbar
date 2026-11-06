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
        $this->assertSame(10, $options->maxBrowsers);
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

    public function testEnabledHistoryRejectsTheRootUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('history.url must not be "/".');

        (new HistoryOptions(true, '/', '/var/debugbar'))->validate();
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

    public function testFromConfigReadsEveryKey(): void
    {
        $options = HistoryOptions::fromConfig(
            [
                'enabled'      => true,
                'url'          => '/history',
                'path'         => '/var/debugbar',
                'max_requests' => 5,
                'ttl_seconds'  => 6,
                'max_browsers' => 7,
                'base_uri'     => '/app1/',
            ]
        );

        $this->assertTrue($options->enabled);
        $this->assertSame('/history', $options->url);
        $this->assertSame('/var/debugbar', $options->path);
        $this->assertSame(5, $options->maxRequests);
        $this->assertSame(6, $options->ttlSeconds);
        $this->assertSame(7, $options->maxBrowsers);
        $this->assertSame('/app1/', $options->baseUri);
    }

    public function testFromConfigUsesTheDefaults(): void
    {
        $options = HistoryOptions::fromConfig([]);

        $this->assertFalse($options->enabled);
        $this->assertSame('/_debugbar/open', $options->url);
        $this->assertSame('', $options->path);
        $this->assertSame(100, $options->maxRequests);
        $this->assertSame(86400, $options->ttlSeconds);
        $this->assertSame(10, $options->maxBrowsers);
        $this->assertSame('/', $options->baseUri);
    }

    public function testHistoryLimitsAreAtLeastOne(): void
    {
        $options = new HistoryOptions(true, '/_debugbar/open', '/var/debugbar', 0, 0, 0);

        $this->assertSame(1, $options->maxRequests);
        $this->assertSame(1, $options->ttlSeconds);
        $this->assertSame(1, $options->maxBrowsers);
    }
}
