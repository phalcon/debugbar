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

namespace Phalcon\Tests\Unit\DebugBar;

use Phalcon\DebugBar\BarOptions;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\Injector;
use Phalcon\DebugBar\Renderer;
use Phalcon\DebugBar\ResponseListener;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Events\Event;
use Phalcon\Http\Request;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\Fixtures\GridCollector;
use Phalcon\Tests\Support\DebugBar\Fixtures\ListCollector;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

use function bin2hex;
use function glob;
use function hash;
use function random_bytes;
use function session_id;
use function session_start;
use function session_write_close;
use function sys_get_temp_dir;
use function unlink;

final class ResponseListenerTest extends AbstractUnitTestCase
{
    public function testDeniedByAccessGateIsNotInjected(): void
    {
        $listener = $this->listener(true, ['10.0.0.1']);

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');

        $listener($this->event(), null, $response);

        $this->assertStringNotContainsString('phalcon-debugbar-data', $response->getContent());
    }

    public function testIgnoresNonResponsePayloads(): void
    {
        $listener = $this->listener(true);

        $listener($this->event(), null, 'not-a-response');

        $this->expectNotToPerformAssertions();
    }

    public function testInjectsWithoutRequestAndWithHeadersOff(): void
    {
        $listener = $this->listener(false);

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');

        $listener($this->event(), null, $response);

        $content = $response->getContent();
        $this->assertStringContainsString('phalcon-debugbar-data', $content);
        $this->assertFalse($response->getHeaders()->get('X-Debug-Bar'));
    }

    #[RunInSeparateProcess]
    public function testInternalHistoryRequestsAreNotRecorded(): void
    {
        $sessionId = 'debugbar-' . bin2hex(random_bytes(8));
        $path      = sys_get_temp_dir() . '/phalcon-debugbar-internal-listener-' . bin2hex(random_bytes(8));
        session_id($sessionId);
        session_start();

        try {
            $request = $this->createMock(RequestInterface::class);
            $request->method('getClientAddress')->willReturn('127.0.0.1');
            $request->method('isAjax')->willReturn(true);
            $request->method('getURI')->willReturn('/_debugbar/open?id=20260909000000-000000-deadbeef');
            $request->method('getMethod')->willReturn('GET');

            $options  = new HistoryOptions(true, '/_debugbar/open', $path);
            $history  = new FilesystemHistory($options);
            $listener = new ResponseListener(
                new DebugBar(),
                new Renderer(),
                new Injector(),
                new AccessGate([], null),
                $request,
                new BarOptions(false, null),
                $history,
                $options
            );
            $response = new Response();
            $response->setContent('{}');

            $listener($this->event(), null, $response);

            $this->assertSame([], $history->find());
        } finally {
            session_write_close();
            $directory = $path . '/' . hash('sha256', $sessionId);
            $files     = glob($directory . '/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }

            @rmdir($directory);
            @unlink($path . '/.gc');
            @rmdir($path);
        }
    }

    #[RunInSeparateProcess]
    public function testRecordsRequestStartTimeFromNativeRequest(): void
    {
        $sessionId = 'debugbar-' . bin2hex(random_bytes(8));
        $path      = sys_get_temp_dir() . '/phalcon-debugbar-native-request-' . bin2hex(random_bytes(8));
        session_id($sessionId);
        session_start();

        $_SERVER['REMOTE_ADDR']        = '127.0.0.1';
        $_SERVER['REQUEST_METHOD']     = 'PATCH';
        $_SERVER['REQUEST_TIME_FLOAT'] = 946782245.123456;
        $_SERVER['REQUEST_URI']        = '/orders/42?full=1';

        try {
            $options  = new HistoryOptions(true, '/_debugbar/open', $path);
            $history  = new FilesystemHistory($options);
            $listener = new ResponseListener(
                new DebugBar(),
                new Renderer(),
                new Injector(),
                new AccessGate([], null),
                new Request(),
                new BarOptions(false, null),
                $history,
                $options
            );
            $response = new Response();
            $response->setStatusCode(202);
            $response->setContent('accepted');

            $listener($this->event(), null, $response);

            $requests = $history->find();
            $this->assertCount(1, $requests);
            $this->assertSame('2000-01-02T03:04:05+00:00', $requests[0]['requested_at']);
        } finally {
            session_write_close();
            $directory = $path . '/' . hash('sha256', $sessionId);
            $files     = glob($directory . '/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }

            @rmdir($directory);
            @unlink($path . '/.gc');
            @rmdir($path);
        }
    }

    #[RunInSeparateProcess]
    public function testRecordsTheCollectedResponseInRequestHistory(): void
    {
        $sessionId = 'debugbar-' . bin2hex(random_bytes(8));
        $path      = sys_get_temp_dir() . '/phalcon-debugbar-listener-' . bin2hex(random_bytes(8));
        session_id($sessionId);
        session_start();

        try {
            $request = $this->createMock(RequestInterface::class);
            $request->method('getClientAddress')->willReturn('127.0.0.1');
            $request->method('isAjax')->willReturn(true);
            $request->method('getURI')->willReturn('/orders/42?full=1');
            $request->method('getMethod')->willReturn('PATCH');
            $_SERVER['REQUEST_TIME_FLOAT'] = 946782245.123456;

            $options  = new HistoryOptions(true, '/_debugbar/open', $path);
            $history  = new FilesystemHistory($options);
            $listener = new ResponseListener(
                new DebugBar(),
                new Renderer(),
                new Injector(),
                new AccessGate([], null),
                $request,
                new BarOptions(false, null),
                $history,
                $options
            );
            $response = new Response();
            $response->setStatusCode(202);
            $response->setContent('accepted');

            $listener($this->event(), null, $response);
            unset($_SERVER['REQUEST_TIME_FLOAT']);
            $listener($this->event(), null, $response);

            $requests = $history->find();
            $this->assertCount(2, $requests);
            $this->assertSame('PATCH', $requests[0]['method']);
            $this->assertSame('/orders/42?full=1', $requests[0]['uri']);
            $this->assertSame(202, $requests[0]['status']);
            $this->assertTrue($requests[0]['ajax']);
            $this->assertSame($requests[0]['stored_at'], $requests[0]['requested_at']);
            $this->assertSame('2000-01-02T03:04:05+00:00', $requests[1]['requested_at']);
            $this->assertNotSame($requests[1]['requested_at'], $requests[1]['stored_at']);
        } finally {
            session_write_close();
            $directory = $path . '/' . hash('sha256', $sessionId);
            $files     = glob($directory . '/*');
            if (false !== $files) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }

            @rmdir($directory);
            @unlink($path . '/.gc');
            @rmdir($path);
        }
    }

    public function testSetsDiagnosticHeaderWhenEnabled(): void
    {
        $bar = new DebugBar();
        $bar->addCollector(new GridCollector())
            ->addCollector(new ListCollector());

        $listener = new ResponseListener(
            $bar,
            new Renderer(),
            new Injector(),
            new AccessGate([], null),
            null,
            new BarOptions(true, null)
        );

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');

        $listener($this->event(), null, $response);

        $this->assertSame('2', $response->getHeaders()->get('X-Debug-Bar'));
    }

    private function event(): Event
    {
        return new Event('application:beforeSendResponse', $this);
    }

    /**
     * @param list<string> $allowedIps
     */
    private function listener(bool $headers, array $allowedIps = []): ResponseListener
    {
        return new ResponseListener(
            new DebugBar(),
            new Renderer(),
            new Injector(),
            new AccessGate($allowedIps, null),
            null,
            new BarOptions($headers, null)
        );
    }
}
