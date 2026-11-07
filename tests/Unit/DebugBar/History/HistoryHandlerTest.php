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

use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\History\HistoryHandler;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

use function json_decode;

final class HistoryHandlerTest extends AbstractUnitTestCase
{
    /**
     * @return array<string, array{false|string}>
     */
    public static function deniedClients(): array
    {
        return [
            'other address' => ['203.0.113.10'],
            'no address'    => [false],
        ];
    }

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function wrongMethods(): array
    {
        return [
            'index with POST'    => ['index', 'POST'],
            'clear with GET'     => ['clear', 'GET'],
            'open with POST'     => ['open', 'POST'],
            'index with nothing' => ['index', null],
            'clear with nothing' => ['clear', null],
        ];
    }

    public function testClearClearsTheHistory(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->once())->method('clear')->willReturn(3);

        $this->assertJsonResponse(
            $this->handler($history, $this->request('DELETE'))->clear(),
            200,
            ['cleared' => 3]
        );
    }

    #[DataProvider('deniedClients')]
    public function testDeniedClientGetsNotFoundAndTheHistoryIsNotRead(false | string $clientIp): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->never())->method('find');
        $history->expects($this->never())->method('clear');

        $this->assertJsonResponse(
            $this->handler($history, $this->request('GET', null, $clientIp), ['127.0.0.1'])->index(),
            404,
            ['error' => 'Not found.']
        );
        $this->assertJsonResponse(
            $this->handler($history, $this->request('DELETE', null, $clientIp), ['127.0.0.1'])->clear(),
            404,
            ['error' => 'Not found.']
        );
    }

    public function testIndexListsTheRequests(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->once())->method('find')->willReturn([['id' => 'stored-request']]);

        $this->assertJsonResponse(
            $this->handler($history, $this->request('GET'))->index(),
            200,
            ['requests' => [['id' => 'stored-request']]]
        );
    }

    public function testIndexReadsOneRequest(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->once())->method('get')->with('stored-request')->willReturn(['payload' => []]);

        $this->assertJsonResponse(
            $this->handler($history, $this->request('GET', 'stored-request'))->index(),
            200,
            ['request' => ['payload' => []]]
        );
    }

    public function testIndexWithANonStringIdReturnsNotFound(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->never())->method('get');

        $this->assertJsonResponse(
            $this->handler($history, $this->request('GET', ['invalid']))->index(),
            404,
            ['error' => 'Request not found.']
        );
    }

    public function testIndexWithAnUnknownIdReturnsNotFound(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->once())->method('get')->with('unknown-id')->willReturn(null);

        $this->assertJsonResponse(
            $this->handler($history, $this->request('GET', 'unknown-id'))->index(),
            404,
            ['error' => 'Request not found.']
        );
    }

    public function testInvalidUtf8InTheBodyIsReplaced(): void
    {
        $history = $this->createMock(History::class);
        $history->method('find')->willReturn([['uri' => "\xB1"]]);

        $this->assertJsonResponse(
            $this->handler($history, $this->request('GET'))->index(),
            200,
            ['requests' => [['uri' => "\u{FFFD}"]]]
        );
    }

    public function testMethodOverrideCannotClearTheHistory(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->never())->method('clear');
        $request = $this->request('POST');
        $request->method('getMethod')->willReturn('DELETE');

        $this->assertJsonResponse(
            $this->handler($history, $request)->open(),
            405,
            ['error' => 'Method not allowed.']
        );
    }

    public function testOpenClearsForADeleteInAnyCase(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->once())->method('clear')->willReturn(1);

        $this->assertJsonResponse(
            $this->handler($history, $this->request('delete'))->open(),
            200,
            ['cleared' => 1]
        );
    }

    public function testOpenListsForAGet(): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->once())->method('find')->willReturn([]);
        $history->expects($this->never())->method('clear');

        $this->assertJsonResponse(
            $this->handler($history, $this->request('GET'))->open(),
            200,
            ['requests' => []]
        );
    }

    public function testResponseIsPrivateJson(): void
    {
        $response = $this->handler($this->createMock(History::class), $this->request('GET'))->index();

        $this->assertSame('application/json; charset=UTF-8', $response->getHeaders()->get('Content-Type'));
        $this->assertSame('no-store, private', $response->getHeaders()->get('Cache-Control'));
    }

    public function testResponseIsTheOneTheHandlerReceived(): void
    {
        $response = new Response();
        $handler  = new HistoryHandler(
            $this->createMock(History::class),
            new AccessGate([], null),
            $this->request('GET'),
            $response
        );

        $this->assertSame($response, $handler->index());
    }

    public function testUnencodableBodyGivesAnEmptyObject(): void
    {
        $history = $this->createMock(History::class);
        $history->method('find')->willReturn([['uri' => NAN]]);

        $response = $this->handler($history, $this->request('GET'))->index();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{}', $response->getContent());
    }

    #[DataProvider('wrongMethods')]
    public function testWrongMethodIsRejected(string $method, ?string $transport): void
    {
        $history = $this->createMock(History::class);
        $history->expects($this->never())->method('find');
        $history->expects($this->never())->method('clear');

        $response = $this->handler($history, $this->request($transport))->{$method}();
        $this->assertInstanceOf(ResponseInterface::class, $response);

        $this->assertJsonResponse($response, 405, ['error' => 'Method not allowed.']);
    }

    /**
     * @param array<string, mixed> $expectedBody
     */
    private function assertJsonResponse(ResponseInterface $response, int $status, array $expectedBody): void
    {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('nosniff', $response->getHeaders()->get('X-Content-Type-Options'));
        $this->assertSame($expectedBody, json_decode($response->getContent(), true));
    }

    /**
     * @param list<string> $allowedIps
     */
    private function handler(History $history, RequestInterface $request, array $allowedIps = []): HistoryHandler
    {
        return new HistoryHandler($history, new AccessGate($allowedIps, null), $request, new Response());
    }

    private function request(
        ?string $method,
        mixed $id = null,
        false | string $clientIp = '127.0.0.1'
    ): MockObject & RequestInterface {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getServer')->with('REQUEST_METHOD')->willReturn($method);
        $request->method('getQuery')->with('id')->willReturn($id);
        $request->method('getClientAddress')->willReturn($clientIp);

        return $request;
    }
}
