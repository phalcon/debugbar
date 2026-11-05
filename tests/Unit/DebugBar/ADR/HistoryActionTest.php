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

use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\DebugBar\ADR\HistoryAction;
use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

use function json_decode;

final class HistoryActionTest extends AbstractUnitTestCase
{
    public function testDeleteClearsTheHistory(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->once())->method('clear')->willReturn(2);

        $response = (new HistoryAction($history, new AccessGate()))($this->request('DELETE'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['cleared' => 2], json_decode($response->getContent(), true));
    }

    public function testDeniedClientGetsNotFound(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->never())->method('find');

        $response = (new HistoryAction($history, new AccessGate(['127.0.0.1'])))($this->request('GET'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['error' => 'Not found.'], json_decode($response->getContent(), true));
    }

    public function testEachRequestGetsANewResponse(): void
    {
        $action = new HistoryAction($this->createMock(History::class), new AccessGate());

        $this->assertNotSame($action($this->request('GET')), $action($this->request('GET')));
    }

    public function testGetListsTheRequests(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->once())->method('find')->willReturn([['id' => 'stored-request']]);

        $response = (new HistoryAction($history, new AccessGate()))($this->request('GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['requests' => [['id' => 'stored-request']]], json_decode($response->getContent(), true));
    }

    private function request(string $method): AttributeRequest
    {
        $request = $this->createMock(AttributeRequest::class);
        $request->method('getServer')->with('REQUEST_METHOD')->willReturn($method);
        $request->method('getClientAddress')->willReturn('203.0.113.10');

        return $request;
    }
}
