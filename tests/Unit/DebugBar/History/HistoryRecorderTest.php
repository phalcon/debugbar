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
use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\History\HistoryRecorder;
use Phalcon\DebugBar\History\RequestMetadata;
use Phalcon\DebugBar\RequestContext;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class HistoryRecorderTest extends AbstractUnitTestCase
{
    private const PAYLOAD = ['data' => [], 'meta' => ['collectors' => 0]];

    /**
     * @var list<array{name: string, options: array<array-key, mixed>}>
     */
    private array $queued = [];

    /**
     * @var list<array{payload: array<array-key, mixed>, metadata: RequestMetadata}>
     */
    private array $saved = [];

    public function testEndpointRequestIsDetected(): void
    {
        $endpointRequest = $this->request('/app1/_debugbar/open?id=entry');
        $otherRequest    = $this->request('/app1/orders');

        $this->assertTrue($this->recorder($endpointRequest)->isEndpointRequest());
        $this->assertFalse($this->recorder($otherRequest)->isEndpointRequest());
    }

    public function testPartsAreAvailableForTheRoutes(): void
    {
        $history  = $this->history();
        $recorder = new HistoryRecorder($history, $this->endpoint(null), $this->cookie(), new Redactor());

        $this->assertSame($history, $recorder->history());
        $this->assertSame('/app1/_debugbar/open', $recorder->endpoint()->url());
    }

    public function testRecordQueuesTheCookieWithTheEndpointPathAndTheSecureFlag(): void
    {
        $request = $this->request('/app1/orders');

        $context = new RequestContext(null, false, true);

        $this->recorder($request)->record(self::PAYLOAD, new Response(), $request, $context);

        $this->assertCount(1, $this->queued);
        $this->assertSame(HistoryCookie::NAME, $this->queued[0]['name']);
        $this->assertSame('/app1/', $this->queued[0]['options']['path']);
        $this->assertTrue($this->queued[0]['options']['secure']);
    }

    public function testRecordSavesThePayloadAndTheRequestValues(): void
    {
        $request  = $this->request('/app1/orders');
        $response = new Response();
        $response->setStatusCode(202);

        $this->recorder($request)->record(self::PAYLOAD, $response, $request, new RequestContext(null, true, false));

        $this->assertCount(1, $this->saved);
        $this->assertSame(self::PAYLOAD, $this->saved[0]['payload']);
        $this->assertSame('PATCH', $this->saved[0]['metadata']->method);
        $this->assertSame('/app1/orders', $this->saved[0]['metadata']->uri);
        $this->assertSame(202, $this->saved[0]['metadata']->status);
        $this->assertTrue($this->saved[0]['metadata']->ajax);
    }

    public function testRecordStoresARedactedUri(): void
    {
        $request = $this->request('/app1/reset?token=abc&page=2');
        $context = new RequestContext(null, false, false);

        $this->recorder($request)->record(self::PAYLOAD, new Response(), $request, $context);

        $this->assertSame('/app1/reset?token=***&page=2', $this->saved[0]['metadata']->uri);
    }

    public function testRecordStoresAResponseWithNoStatusAsOk(): void
    {
        $request = $this->request('/app1/orders');

        $context = new RequestContext(null, false, false);

        $this->recorder($request)->record(self::PAYLOAD, new Response(), $request, $context);

        $this->assertSame(200, $this->saved[0]['metadata']->status);
    }

    public function testRecordWithNoRequestSavesNothingAndStillQueuesTheCookie(): void
    {
        $this->recorder(null)->record(self::PAYLOAD, new Response(), null, new RequestContext(null, false, false));

        $this->assertSame([], $this->saved);
        $this->assertCount(1, $this->queued);
    }

    private function cookie(): HistoryCookie
    {
        return new HistoryCookie(
            null,
            function (string $name, string $value, array $options): bool {
                $this->queued[] = ['name' => $name, 'options' => $options];

                return true;
            }
        );
    }

    private function endpoint(?RequestInterface $request): HistoryEndpoint
    {
        return new HistoryEndpoint(
            new HistoryOptions(true),
            fn (): ?RequestInterface => $request,
            fn (): string => '/app1/'
        );
    }

    private function history(): History
    {
        $history = $this->createMock(History::class);
        $history->method('save')->willReturnCallback(
            function (array $payload, RequestMetadata $metadata): ?string {
                $this->saved[] = ['payload' => $payload, 'metadata' => $metadata];

                return null;
            }
        );

        return $history;
    }

    private function recorder(?RequestInterface $request): HistoryRecorder
    {
        return new HistoryRecorder($this->history(), $this->endpoint($request), $this->cookie(), new Redactor());
    }

    private function request(string $uri): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getURI')->willReturn($uri);
        $request->method('getMethod')->willReturn('PATCH');

        return $request;
    }
}
