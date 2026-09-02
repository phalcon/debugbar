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

use Phalcon\DebugBar\Controllers\OpenHandlerController;
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

final class OpenHandlerControllerTest extends AbstractUnitTestCase
{
    #[RunInSeparateProcess]
    public function testListsAndLoadsRequestsFromTheCurrentSession(): void
    {
        $sessionId = 'debugbar-' . bin2hex(random_bytes(8));
        $path      = sys_get_temp_dir() . '/phalcon-debugbar-controller-' . bin2hex(random_bytes(8));
        session_id($sessionId);
        session_start();

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $history = new FilesystemHistory(new HistoryOptions(true, '/_debugbar/open', $path));
            $id      = $history->save(
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

            $_GET = ['id' => $id];
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
            $_GET = [];
            $clear = $this->execute($history, 'clear');
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
            @rmdir($path);
        }
    }

    private function execute(FilesystemHistory $history, string $action = 'index'): Response
    {
        $container = new Di();
        $response  = new Response();
        $container->setShared('request', new Request());
        $container->setShared('response', $response);
        $container->setShared(Provider::HISTORY_SERVICE, $history);
        $container->setShared(Provider::ACCESS_GATE_SERVICE, new AccessGate([], null));

        $controller = new OpenHandlerController();
        $controller->setDI($container);
        if ('clear' === $action) {
            $controller->clearAction();
        } else {
            $controller->indexAction();
        }

        return $response;
    }
}
