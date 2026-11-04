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

use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\Http\RequestInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class HistoryEndpointTest extends AbstractUnitTestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function baseUris(): array
    {
        return [
            'root'                => ['/', '/'],
            'path'                => ['/app1/', '/app1/'],
            'no trailing slash'   => ['/app1', '/app1/'],
            'absolute'            => ['https://example.test/app1/', '/app1/'],
            'absolute, no path'   => ['https://example.test', '/'],
            'empty'               => ['', '/'],
        ];
    }

    public function testBaseUriAndRequestAreReadAtCallTime(): void
    {
        $baseUri = '/initial/';
        $request = null;
        $final   = $this->createMock(RequestInterface::class);
        $final->method('getURI')->willReturn('/final/_debugbar/open');
        $endpoint = new HistoryEndpoint(
            new HistoryOptions(true),
            function () use (&$request): ?RequestInterface {
                return $request;
            },
            function () use (&$baseUri): string {
                return $baseUri;
            }
        );

        $this->assertSame('/initial/_debugbar/open', $endpoint->url());
        $this->assertFalse($endpoint->matches());

        $baseUri = '/final/';
        $request = $final;

        $this->assertSame('/final/_debugbar/open', $endpoint->url());
        $this->assertTrue($endpoint->matches());
    }

    #[DataProvider('baseUris')]
    public function testCookiePathIsThePathOfTheBaseUri(string $baseUri, string $expected): void
    {
        $this->assertSame($expected, $this->endpoint(null, $baseUri)->cookiePath());
    }

    public function testNoRequestUsesTheRootPathAndDoesNotMatch(): void
    {
        $endpoint = $this->endpoint(null);

        $this->assertSame('/', $endpoint->cookiePath());
        $this->assertSame('/_debugbar/open', $endpoint->url());
        $this->assertFalse($endpoint->matches());
    }

    public function testRequestForTheEndpointMatches(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getURI')->willReturn('/app1/_debugbar/open?id=entry');

        $this->assertTrue($this->endpoint($request, '/app1/')->matches());
    }

    public function testRequestWithTheEndpointInItsQueryDoesNotMatch(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getURI')->willReturn('/app1/orders/open?next=/_debugbar/open');

        $this->assertFalse($this->endpoint($request, 'http://localhost:8080/app1/')->matches());
    }

    private function endpoint(?RequestInterface $request, string $baseUri = '/'): HistoryEndpoint
    {
        return new HistoryEndpoint(
            new HistoryOptions(true),
            fn (): ?RequestInterface => $request,
            fn (): string => $baseUri
        );
    }
}
