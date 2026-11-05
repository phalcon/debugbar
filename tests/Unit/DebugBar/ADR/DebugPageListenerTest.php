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

use Phalcon\Debug;
use Phalcon\Debug\Contracts\Renderer as DebugRenderer;
use Phalcon\DebugBar\ADR\DebugPageListener;
use Phalcon\DebugBar\BarOptions;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Injector;
use Phalcon\DebugBar\Renderer;
use Phalcon\DebugBar\ResponseListener;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Events\Event;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use RuntimeException;

final class DebugPageListenerTest extends AbstractUnitTestCase
{
    private const APP_BODY = '<body>application error page';

    private const APP_PAGE = '<html>' . self::APP_BODY . '</body></html>';

    /**
     * The debug page lists the source of the files in the backtrace, this file
     * also. It escapes the source, so these strings of raw markup do not match it.
     */
    private const BAR = '<div id="phalcon-debugbar">';

    private const DEBUG_PAGE = "data-tab='backtrace'";

    private const MESSAGE = 'the action failed';

    public function testAjaxRequestKeepsTheResponse(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('isAjax')->willReturn(true);
        $response = $this->response();

        $this->invoke($this->listener($this->barWithException(), $request), $response);

        $this->assertSame(self::APP_PAGE, $response->getContent());
    }

    public function testDeniedClientKeepsTheResponse(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getClientAddress')->willReturn('203.0.113.10');
        $response = $this->response();

        $this->invoke($this->listener($this->barWithException(), $request, ['127.0.0.1']), $response);

        $this->assertSame(self::APP_PAGE, $response->getContent());
    }

    public function testDeniedClientWithNoAddressKeepsTheResponse(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getClientAddress')->willReturn(false);
        $response = $this->response();

        $this->invoke($this->listener($this->barWithException(), $request, ['127.0.0.1']), $response);

        $this->assertSame(self::APP_PAGE, $response->getContent());
    }

    public function testFailureInTheDebugPageKeepsTheApplicationPage(): void
    {
        $renderer = $this->createMock(DebugRenderer::class);
        $renderer->method('render')->willThrowException(new RuntimeException('renderer failed'));
        $response = $this->response();

        $this->invoke(
            $this->listener($this->barWithException(), null, [], (new Debug())->setRenderer($renderer)),
            $response
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString(self::APP_BODY, $response->getContent());
        $this->assertStringContainsString(self::BAR, $response->getContent());
    }

    public function testJsonResponseKeepsTheResponse(): void
    {
        $response = $this->response();
        $response->setContentType('application/json');
        $response->setContent('{"error":true}');

        $this->invoke($this->listener($this->barWithException()), $response);

        $this->assertSame('{"error":true}', $response->getContent());
    }

    public function testNoExceptionKeepsThePage(): void
    {
        $response = $this->response();

        $this->invoke($this->listener(new DebugBar()), $response);

        $this->assertStringContainsString(self::APP_BODY, $response->getContent());
        $this->assertStringContainsString(self::BAR, $response->getContent());
    }

    public function testPayloadThatIsNotAResponseIsIgnored(): void
    {
        $bar = $this->barWithException();

        ($this->listener($bar))(new Event('application:afterHandle', $this), null, null);

        $this->assertSame([], $bar->getData()['data']);
    }

    public function testResponseWithNoStatusKeepsThePage(): void
    {
        $response = new Response();
        $response->setContent(self::APP_PAGE);

        $this->invoke($this->listener($this->barWithException()), $response);

        $this->assertStringContainsString(self::APP_BODY, $response->getContent());
    }

    public function testServerErrorShowsTheDebugPage(): void
    {
        $response = $this->response();

        $this->invoke($this->listener($this->barWithException()), $response);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString(self::MESSAGE, $response->getContent());
        $this->assertStringNotContainsString(self::APP_BODY, $response->getContent());
    }

    public function testServerErrorWithNoRequestShowsTheDebugPage(): void
    {
        $response = $this->response();

        $this->invoke($this->listener($this->barWithException(), null), $response);

        $this->assertStringContainsString(self::MESSAGE, $response->getContent());
    }

    public function testStatusBelowTheServerErrorsKeepsThePage(): void
    {
        $response = $this->response(499);

        $this->invoke($this->listener($this->barWithException()), $response);

        $this->assertStringContainsString(self::APP_BODY, $response->getContent());
        $this->assertStringNotContainsString(self::DEBUG_PAGE, $response->getContent());
    }

    public function testTheBarIsNotInTheDebugPage(): void
    {
        $response = $this->response();

        $this->invoke($this->listener($this->barWithException()), $response);

        $this->assertStringNotContainsString(self::BAR, $response->getContent());
    }

    public function testTheInnerListenerRunsFirst(): void
    {
        $bar      = $this->barWithException();
        $response = $this->response();

        $this->invoke($this->listener($bar), $response);

        $this->assertArrayHasKey('collectors', $bar->getData()['meta']);
        $this->assertTrue($response->getHeaders()->has('X-Debug-Bar'));
    }

    private function barWithException(): DebugBar
    {
        return (new DebugBar())->addException(new RuntimeException(self::MESSAGE));
    }

    private function invoke(DebugPageListener $listener, Response $response): void
    {
        $listener(new Event('application:afterHandle', $this), null, $response);
    }

    /**
     * @param list<string> $allowedIps
     */
    private function listener(
        DebugBar $bar,
        ?RequestInterface $request = null,
        array $allowedIps = [],
        ?Debug $debug = null
    ): DebugPageListener {
        $accessGate = new AccessGate($allowedIps);

        return new DebugPageListener(
            new ResponseListener(
                $bar,
                new Renderer(),
                new Injector(),
                $accessGate,
                $request,
                new BarOptions(true, null)
            ),
            $bar,
            $debug ?? new Debug(),
            $accessGate,
            $request
        );
    }

    private function response(int $status = 500): Response
    {
        $response = new Response();
        $response->setStatusCode($status);
        $response->setContentType('text/html');
        $response->setContent(self::APP_PAGE);

        return $response;
    }
}
