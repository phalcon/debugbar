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

use InvalidArgumentException;
use Phalcon\ADR\Container\AdrProvider;
use Phalcon\Container\Container;
use Phalcon\Container\Exceptions\CannotExtendResolved;
use Phalcon\Contracts\ADR\Router\Router;
use Phalcon\Contracts\Events\Manager as ManagerContract;
use Phalcon\DebugBar\ADR\HistoryRouter;
use Phalcon\DebugBar\ADR\Provider;
use Phalcon\DebugBar\Core;
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\DebugBar\ResponseListener;
use Phalcon\Events\Manager;
use Phalcon\Http\ResponseInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\ADR\Front;
use Phalcon\Tests\Support\DebugBar\ADR\RecordingEmitter;
use PHPUnit\Framework\Attributes\BackupGlobals;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;
use RuntimeException;
use SplFileInfo;

use function bin2hex;
use function dirname;
use function is_array;
use function is_dir;
use function json_decode;
use function random_bytes;
use function rmdir;
use function str_repeat;
use function unlink;

/**
 * Real requests through a real ADR front controller, with request history on.
 *
 * @phpstan-import-type provider_config from DebugBarTypes
 */
#[BackupGlobals(true)]
final class ProviderHistoryTest extends AbstractUnitTestCase
{
    private const ENV_VAR = 'DEBUGBAR_TEST_ENV';

    private const URL = '/_debugbar/open';

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        Debug::setBar(null);

        $this->path                   = dirname(__DIR__, 3) . '/_output/adr-history-' . bin2hex(random_bytes(8));
        $_ENV[self::ENV_VAR]          = 'dev';
        $_COOKIE[HistoryCookie::NAME] = str_repeat('a', 64);
        $_GET                         = [];
    }

    protected function tearDown(): void
    {
        Debug::setBar(null);

        if (is_dir($this->path)) {
            $items = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            /** @var SplFileInfo $item */
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }

            rmdir($this->path);
        }

        parent::tearDown();
    }

    public function testBaseUriPrefixesTheEndpoint(): void
    {
        $config = $this->config(['base_uri' => '/app1/']);

        $this->assertSame(
            ['requests' => []],
            $this->body($this->request('/app1' . self::URL, 'GET', $config), 200)
        );

        $this->assertSame(404, $this->request(self::URL, 'GET', $config)->getStatusCode());
        $this->assertSame('/app1' . self::URL, $this->panelUrl());
    }

    public function testBlockedEnvironmentDoesNotValidateTheHistoryOptions(): void
    {
        $_ENV[self::ENV_VAR] = 'production';

        (new Provider(['env' => ['var' => self::ENV_VAR], 'history' => ['enabled' => true]]))->provide(new Container());

        $this->assertNull(Debug::getBar());
    }

    public function testBlockedEnvironmentLeavesTheRouterUnchanged(): void
    {
        $_ENV[self::ENV_VAR] = 'production';

        $this->assertNotInstanceOf(HistoryRouter::class, $this->container($this->config())->get(Router::class));
    }

    public function testClearRemovesTheStoredRequests(): void
    {
        $this->request('/page', 'GET', $this->config());

        $this->assertSame(['cleared' => 1], $this->body($this->request(self::URL, 'DELETE', $this->config()), 200));
        $this->assertSame(['requests' => []], $this->body($this->request(self::URL, 'GET', $this->config()), 200));
    }

    public function testDeniedClientGetsNotFound(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $config                 = $this->config() + ['access' => ['allow_ips' => ['127.0.0.1']]];

        $this->assertSame(['error' => 'Not found.'], $this->body($this->request(self::URL, 'GET', $config), 404));
    }

    public function testDisabledCollectorRegistersNoEndpoint(): void
    {
        $response = $this->request(self::URL, 'GET', $this->config() + ['collectors' => ['history' => false]]);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($this->bar()->hasCollector('history'));
    }

    public function testDisabledHistoryLeavesTheRouterUnchanged(): void
    {
        $container = $this->container(['env' => ['var' => self::ENV_VAR]]);

        $this->assertNotInstanceOf(HistoryRouter::class, $container->get(Router::class));
        $this->assertFalse($this->bar()->hasCollector('history'));
    }

    public function testEnabledHistoryDecoratesTheRouter(): void
    {
        $this->assertInstanceOf(HistoryRouter::class, $this->container($this->config())->get(Router::class));
    }

    public function testEnabledHistoryWithNoPathThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('history.path is required');

        (new Provider(['env' => ['var' => self::ENV_VAR], 'history' => ['enabled' => true]]))->provide(new Container());
    }

    public function testHistoryRequestIsNotRecorded(): void
    {
        $this->request(self::URL, 'GET', $this->config());

        $this->assertSame(['requests' => []], $this->body($this->request(self::URL, 'GET', $this->config()), 200));
    }

    public function testHistoryResponseIsJsonWithNoBar(): void
    {
        $response = $this->request(self::URL, 'GET', $this->config());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"requests":[]}', $response->getContent());
        $this->assertSame('application/json; charset=UTF-8', $response->getHeaders()->get('Content-Type'));
        $this->assertFalse($response->getHeaders()->has('X-Debug-Bar'));
    }

    public function testListenerReceivesTheHistoryCookie(): void
    {
        $provider  = new Provider($this->config());
        $container = new Container();
        (new AdrProvider())->provide($container);
        $provider->provide($container);

        // The CLI discards response headers, so a test cannot read the `Set-Cookie` header.
        $core = (new ReflectionProperty(Provider::class, 'core'))->getValue($provider);
        $this->assertInstanceOf(Core::class, $core);
        $listener = (new ReflectionProperty(Core::class, 'listener'))->getValue($core);
        $this->assertInstanceOf(ResponseListener::class, $listener);

        $this->assertInstanceOf(
            HistoryCookie::class,
            (new ReflectionProperty(ResponseListener::class, 'historyCookie'))->getValue($listener)
        );
    }

    public function testListsAndReadsAStoredRequest(): void
    {
        $this->request('/page', 'GET', $this->config());

        $list     = $this->body($this->request(self::URL, 'GET', $this->config()), 200);
        $requests = $list['requests'] ?? null;
        if (!is_array($requests) || !is_array($requests[0] ?? null)) {
            $this->fail('The list should have a request.');
        }

        $this->assertCount(1, $requests);
        $this->assertSame('/page', $requests[0]['uri']);

        $_GET   = ['id' => $requests[0]['id']];
        $detail = $this->body($this->request(self::URL, 'GET', $this->config()), 200);

        $this->assertArrayHasKey('request', $detail);
    }

    public function testManagerOutsideTheEventsManagerInterfaceRegistersNoEndpoint(): void
    {
        $manager   = $this->createMock(ManagerContract::class);
        $container = new Container();
        (new AdrProvider())->provide($container);
        $container->set(ManagerContract::class, fn (): ManagerContract => $manager);

        (new Provider($this->config()))->provide($container);

        $this->assertFalse($this->bar()->hasCollector('history'));
        $this->assertNotInstanceOf(HistoryRouter::class, $container->get(Router::class));
    }

    public function testOtherUrlsReachTheApplicationAndShowThePanel(): void
    {
        $response = $this->request('/page', 'GET', $this->config());

        $this->assertStringStartsWith('<html><body>page', $response->getContent());
        $this->assertStringContainsString('phalcon-debugbar-data', $response->getContent());
        $this->assertSame(self::URL, $this->panelUrl());
    }

    public function testPostIsRejected(): void
    {
        $this->assertSame(
            ['error' => 'Method not allowed.'],
            $this->body($this->request(self::URL, 'POST', $this->config()), 405)
        );
    }

    public function testResolvedRouterCannotBeDecorated(): void
    {
        $container = new Container();
        (new AdrProvider())->provide($container);
        $container->get(Router::class);

        $this->expectException(CannotExtendResolved::class);

        (new Provider($this->config()))->provide($container);
    }

    public function testRouterMissingFromTheContainerRegistersNoEndpoint(): void
    {
        $container = new Container();
        $container->set(ManagerContract::class, fn (): ManagerContract => new Manager());

        (new Provider($this->config()))->provide($container);

        $this->assertFalse($this->bar()->hasCollector('history'));
    }

    public function testUnwritableStorageThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('history.path must be writable');

        $this->container(['env' => ['var' => self::ENV_VAR], 'history' => ['enabled' => true, 'path' => __FILE__]]);
    }

    private function bar(): DebugBar
    {
        $bar = Debug::getBar();
        if (!$bar instanceof DebugBar) {
            $this->fail('Expected the debug bar to be booted.');
        }

        return $bar;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function body(ResponseInterface $response, int $status): array
    {
        $this->assertSame($status, $response->getStatusCode());
        $this->assertSame('nosniff', $response->getHeaders()->get('X-Content-Type-Options'));

        $body = json_decode($response->getContent(), true);
        $this->assertIsArray($body);

        return $body;
    }

    /**
     * @param array{base_uri?: string} $history
     *
     * @return array{env: array{var: string}, history: array{enabled: true, path: string, base_uri?: string}}
     */
    private function config(array $history = []): array
    {
        return [
            'env'     => ['var' => self::ENV_VAR],
            'history' => ['enabled' => true, 'path' => $this->path] + $history,
        ];
    }

    /**
     * @param provider_config $config
     */
    private function container(array $config): Container
    {
        $container = new Container();
        (new AdrProvider())->provide($container);
        (new Provider($config))->provide($container);

        return $container;
    }

    private function panelUrl(): mixed
    {
        $panel = $this->bar()->collect()['data']['history']['panel'];
        $this->assertIsArray($panel);

        return $panel['url'];
    }

    /**
     * @param provider_config $config
     */
    private function request(string $uri, string $method, array $config): ResponseInterface
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;

        $emitter = new RecordingEmitter();

        $this->assertSame(0, (new Front($emitter, $config))->run());
        if (null === $emitter->response) {
            $this->fail('Expected the front controller to emit a response.');
        }

        return $emitter->response;
    }
}
