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

namespace Phalcon\Tests\Unit\DebugBar\ADR;

use Phalcon\ADR\Router\RouterMatch;
use Phalcon\Contracts\ADR\Router\Router;
use Phalcon\DebugBar\ADR\HistoryAction;
use Phalcon\DebugBar\ADR\HistoryRouter;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\Http\RequestInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class HistoryRouterTest extends AbstractUnitTestCase
{
    public function testHistoryUrlMatchesTheAction(): void
    {
        $inner = $this->createMock(Router::class);
        $inner->expects($this->never())->method('match');
        $request = $this->request('/_debugbar/open?id=entry');

        $match = $this->router($inner, $request)->match($request);

        $this->assertNotNull($match);
        $this->assertSame(HistoryAction::class, $match->getAction());
        $this->assertSame([], $match->getAttributes());
        $this->assertSame([], $match->getMiddleware());
    }

    public function testLookupsGoToTheInnerRouter(): void
    {
        $inner = $this->createMock(Router::class);
        $inner->expects($this->once())->method('candidatesFor')->with('GET', '/a')->willReturn(['A']);
        $inner->expects($this->once())->method('classFor')->with('GET', '/a')->willReturn('A');
        $inner->expects($this->once())->method('methodFor')->with('A')->willReturn('GET');
        $inner->expects($this->once())->method('pathFor')->with('A')->willReturn('/a');
        $router = $this->router($inner, $this->request('/a'));

        $this->assertSame(['A'], $router->candidatesFor('GET', '/a'));
        $this->assertSame('A', $router->classFor('GET', '/a'));
        $this->assertSame('GET', $router->methodFor('A'));
        $this->assertSame('/a', $router->pathFor('A'));
    }

    public function testOtherUrlsGoToTheInnerRouter(): void
    {
        $match   = new RouterMatch(self::class);
        $request = $this->request('/orders?next=/_debugbar/open');
        $inner   = $this->createMock(Router::class);
        $inner->expects($this->once())->method('match')->with($request)->willReturn($match);

        $this->assertSame($match, $this->router($inner, $request)->match($request));
    }

    public function testSettersGoToTheInnerRouterAndReturnTheDecorator(): void
    {
        $inner = $this->createMock(Router::class);
        $inner->expects($this->once())->method('setActionDirectory')->with('/actions')->willReturnSelf();
        $inner->expects($this->once())->method('setBaseNamespace')->with('App')->willReturnSelf();
        $inner->expects($this->once())->method('setMiddlewareMap')->with(['\\A\\' => [self::class]])->willReturnSelf();
        $inner->expects($this->once())->method('setWordSeparator')->with('_')->willReturnSelf();
        $router = $this->router($inner, $this->request('/a'));

        $this->assertSame($router, $router->setActionDirectory('/actions'));
        $this->assertSame($router, $router->setBaseNamespace('App'));
        $this->assertSame($router, $router->setMiddlewareMap(['\\A\\' => [self::class]]));
        $this->assertSame($router, $router->setWordSeparator('_'));
    }

    public function testUrlWithNoMatchReturnsNull(): void
    {
        $request = $this->request('/missing');
        $inner   = $this->createMock(Router::class);
        $inner->method('match')->willReturn(null);

        $this->assertNull($this->router($inner, $request)->match($request));
    }

    private function request(string $uri): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getURI')->willReturn($uri);

        return $request;
    }

    private function router(Router $inner, RequestInterface $request): HistoryRouter
    {
        return new HistoryRouter(
            $inner,
            new HistoryEndpoint(
                new HistoryOptions(true),
                fn (): RequestInterface => $request,
                fn (): string => '/'
            )
        );
    }
}
