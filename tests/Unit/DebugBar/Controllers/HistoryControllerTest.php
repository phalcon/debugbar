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

namespace Phalcon\Tests\Unit\DebugBar\Controllers;

use Phalcon\DebugBar\Controllers\HistoryController;
use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\History\RequestMetadata;
use Phalcon\DebugBar\Provider;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Di\Di;
use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;
use stdClass;
use Throwable;

use function bin2hex;
use function glob;
use function hash;
use function json_decode;
use function random_bytes;
use function session_id;
use function session_start;
use function session_write_close;
use function sys_get_temp_dir;
use function unlink;

final class HistoryControllerTest extends AbstractUnitTestCase
{
    public function testActionsHideHistoryWhenAccessIsDenied(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $history                = new FilesystemHistory(new HistoryOptions());

        foreach (['indexAction', 'clearAction'] as $action) {
            $response = $this->executeWithServices(
                $action,
                new Request(),
                new Response(),
                $history,
                new AccessGate(['127.0.0.1'], null)
            );

            $this->assertJsonResponse($response, 404, ['error' => 'Not found.']);
        }
    }

    public function testActionsRejectUnsupportedMethods(): void
    {
        $history = new FilesystemHistory(new HistoryOptions());

        foreach ([['indexAction', 'POST'], ['clearAction', 'GET']] as [$action, $method]) {
            $_SERVER['REQUEST_METHOD'] = $method;
            $response                  = $this->executeWithServices(
                $action,
                new Request(),
                new Response(),
                $history,
                new AccessGate([], null)
            );

            $this->assertJsonResponse($response, 405, ['error' => 'Method not allowed.']);
        }
    }

    public function testActionsReportUnavailableHistoryServices(): void
    {
        foreach (['indexAction', 'clearAction'] as $action) {
            $response = $this->executeWithServices(
                $action,
                new stdClass(),
                new Response(),
                new stdClass(),
                new stdClass()
            );

            $this->assertJsonResponse($response, 500, ['error' => 'History is unavailable.']);
        }
    }

    #[RunInSeparateProcess]
    public function testActionsRequireADiContainer(): void
    {
        Di::reset();
        $controller = new HistoryController();

        foreach (['indexAction', 'clearAction'] as $action) {
            try {
                $controller->{$action}();
                $this->fail('Expected the controller to require a DI container.');
            } catch (Throwable $exception) {
                $this->assertContains($exception->getMessage(), [
                    'The History controller requires a DI container.',
                    'A dependency injection container is required to access internal services',
                ]);
            }
        }
    }

    public function testActionsRequireAResponseService(): void
    {
        $history = new FilesystemHistory(new HistoryOptions());

        foreach (['indexAction', 'clearAction'] as $action) {
            try {
                $this->executeWithServices($action, new Request(), new stdClass(), $history, new AccessGate([], null));
                $this->fail('Expected the controller to require a response service.');
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'The response service must implement ResponseInterface.',
                    $exception->getMessage()
                );
            }
        }
    }

    public function testInvalidStoredRequestIdentifierReturnsNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        foreach (['', ['invalid']] as $id) {
            $_GET     = ['id' => $id];
            $response = $this->executeWithServices(
                'indexAction',
                new Request(),
                new Response(),
                new FilesystemHistory(new HistoryOptions()),
                new AccessGate([], null)
            );

            $this->assertJsonResponse($response, 404, ['error' => 'Request not found.']);
        }
    }

    #[RunInSeparateProcess]
    public function testListsAndLoadsRequestsFromTheCurrentSession(): void
    {
        $sessionId = 'debugbar-' . bin2hex(random_bytes(8));
        $path      = sys_get_temp_dir() . '/phalcon-debugbar-controller-' . bin2hex(random_bytes(8));
        session_id($sessionId);
        session_start();

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $history                   = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));
            $id                        = $history->save(
                ['data' => [], 'meta' => ['collectors' => 0]],
                new RequestMetadata('GET', '/orders', 200, false)
            );
            $this->assertIsString($id);

            $_GET = [];
            $list = $this->execute($history);
            $this->assertSame(200, $list->getStatusCode());
            $listBody = json_decode($list->getContent(), true);
            $this->assertIsArray($listBody);
            $requests = $listBody['requests'];
            $this->assertIsArray($requests);
            $this->assertCount(1, $requests);

            $_GET   = ['id' => $id];
            $detail = $this->execute($history);
            $this->assertSame(200, $detail->getStatusCode());
            $detailBody = json_decode($detail->getContent(), true);
            $this->assertIsArray($detailBody);
            $request = $detailBody['request'];
            $this->assertIsArray($request);
            $meta = $request['meta'];
            $this->assertIsArray($meta);
            $this->assertSame($id, $meta['id']);
            $this->assertSame('no-store, private', $detail->getHeaders()->get('Cache-Control'));

            $_SERVER['REQUEST_METHOD'] = 'DELETE';
            $_GET                      = [];
            $clear                     = $this->execute($history, 'clear');
            $this->assertSame(200, $clear->getStatusCode());
            $clearBody = json_decode($clear->getContent(), true);
            $this->assertIsArray($clearBody);
            $this->assertSame(1, $clearBody['cleared']);
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

    public function testMissingStoredRequestReturnsNotFound(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET                      = ['id' => '20260903120000-123456-deadbeef'];

        $response = $this->executeWithServices(
            'indexAction',
            new Request(),
            new Response(),
            new FilesystemHistory(new HistoryOptions()),
            new AccessGate([], null)
        );

        $this->assertJsonResponse($response, 404, ['error' => 'Request not found.']);
    }

    /**
     * @param array<string, mixed> $expectedBody
     */
    private function assertJsonResponse(Response $response, int $status, array $expectedBody): void
    {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame($expectedBody, json_decode($response->getContent(), true));
    }

    private function execute(FilesystemHistory $history, string $action = 'index'): Response
    {
        $response  = new Response();

        return $this->executeWithServices(
            'clear' === $action ? 'clearAction' : 'indexAction',
            new Request(),
            $response,
            $history,
            new AccessGate([], null)
        );
    }

    private function executeWithServices(
        string $action,
        object $request,
        object $response,
        object $history,
        object $access
    ): Response {
        $container = new Di();
        $container->setShared('request', $request);
        $container->setShared('response', $response);
        $container->setShared(Provider::HISTORY_SERVICE, $history);
        $container->setShared(Provider::ACCESS_GATE_SERVICE, $access);

        $controller = new HistoryController();
        $controller->setDI($container);
        $result = $controller->{$action}();

        $this->assertInstanceOf(Response::class, $result);

        return $result;
    }
}
