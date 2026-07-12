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

namespace Phalcon\Tests\Unit\DebugBar\Collector;

use Phalcon\DebugBar\Collector\RequestCollector;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Http\RequestInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;

final class RequestCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testEmptyPanelWhenNoRequest(): void
    {
        $collector = new RequestCollector(null, new Redactor());

        $this->assertSame('request', $collector->getName());
        $this->assertSame([], $collector->collect()['panel']);
        $this->assertPanelContract($collector);
    }

    public function testNonArrayQueryOrPostIsIgnored(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getURI')->willReturn('/');
        $request->method('getQuery')->willReturn('not-an-array');
        $request->method('getPost')->willReturn(null);
        $request->method('getHeaders')->willReturn([]);

        $panel = (new RequestCollector($request, new Redactor()))->collect()['panel'];

        $this->assertSame('GET', $panel['Method']);
        $this->assertArrayNotHasKey('Query.0', $panel);
    }

    public function testRequestDataIsFlattenedAndRedacted(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getURI')->willReturn('/login');
        $request->method('getQuery')->willReturn(['q' => 'phalcon', 'n' => null]);
        $request->method('getPost')->willReturn(['username' => 'sarah-connor', 'password' => 'secret-value']);
        $request->method('getHeaders')->willReturn([
            'User-Agent'    => 'phalcon-test',
            'Authorization' => 'Bearer token-value',
        ]);

        $panel = (new RequestCollector($request, new Redactor()))->collect()['panel'];

        $this->assertSame('POST', $panel['Method']);
        $this->assertSame('/login', $panel['URI']);
        $this->assertSame('phalcon', $panel['Query.q']);
        $this->assertSame('', $panel['Query.n']);
        $this->assertSame('sarah-connor', $panel['Post.username']);
        $this->assertSame('***', $panel['Post.password']);
        $this->assertSame('phalcon-test', $panel['Headers.User-Agent']);
        $this->assertSame('***', $panel['Headers.Authorization']);
    }
}
