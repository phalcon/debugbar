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

use InvalidArgumentException;
use Phalcon\Config\Config;
use Phalcon\DebugBar\Controllers\HistoryController;
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\Provider;
use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phalcon\Di\FactoryDefault;
use Phalcon\Events\Manager;
use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Url;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use stdClass;

use function array_keys;
use function is_array;
use function json_decode;
use function parse_url;
use function putenv;
use function sys_get_temp_dir;

use const PHP_URL_PATH;

#[BackupGlobals(true)]
final class ProviderTest extends AbstractUnitTestCase
{
    private const ENV_VAR    = 'DEBUGBAR_TEST_ENV';

    private const GETENV_VAR = 'DEBUGBAR_TEST_GETENV';

    protected function setUp(): void
    {
        parent::setUp();

        Debug::setBar(null);
    }

    protected function tearDown(): void
    {
        Debug::setBar(null);

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string, int, string}>
     */
    public static function historyEndpointRequests(): array
    {
        return [
            'root GET'         => ['/', 'GET', 200, '{"requests":[]}'],
            'absolute base'    => ['http://localhost:8080/', 'GET', 200, '{"requests":[]}'],
            'subfolder GET'    => ['/app1/', 'GET', 200, '{"requests":[]}'],
            'subfolder DELETE' => ['/app1/', 'DELETE', 200, '{"cleared":0}'],
            'subfolder POST'   => ['/app1/', 'POST', 405, '{"error":"Method not allowed."}'],
        ];
    }

    public function testAccessAllowListBlocksNonMatchingClient(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $em                  = new Manager();
        $app                 = $this->application($em);

        (new Provider($app, [
            'env'    => ['var' => self::ENV_VAR],
            'access' => ['allow_ips' => ['203.0.113.1']],
        ]))->boot();

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');
        $em->fire('application:beforeSendResponse', $app, $response);

        $this->assertStringNotContainsString('phalcon-debugbar-data', $response->getContent());
    }

    public function testAllowedEnvironmentValidatesHistoryStorage(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('history.path is required');

        (new Provider($this->application(new Manager()), [
            'env'     => ['var' => self::ENV_VAR],
            'history' => ['enabled' => true],
        ]))->boot();
    }

    public function testBlockedEnvironmentDoesNotValidateHistoryStorage(): void
    {
        $_ENV[self::ENV_VAR] = 'production';

        (new Provider($this->application(new Manager()), [
            'env'     => ['var' => self::ENV_VAR],
            'history' => ['enabled' => true],
        ]))->boot();

        $this->assertNull(Debug::getBar());
    }

    public function testBlockedEnvironmentIsSilentByDefault(): void
    {
        $_ENV[self::ENV_VAR] = 'production';

        $this->provider($this->application(new Manager()))->boot();

        $this->assertNull(Debug::getBar());
    }

    public function testBootInjectsTheBarOnBeforeSendResponse(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $em                  = new Manager();
        $app                 = $this->application($em);

        $this->provider($app)->boot();

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');
        $em->fire('application:beforeSendResponse', $app, $response);

        $this->assertStringContainsString('phalcon-debugbar-data', $response->getContent());
        $this->assertInstanceOf(DebugBar::class, Debug::getBar());
    }

    public function testBootWithoutEventsManagerSetsFacadeOnly(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $app                 = new Application(new Di());

        $this->provider($app)->boot();

        $this->assertInstanceOf(DebugBar::class, Debug::getBar());
    }

    public function testConfigCollectorRedactsSensitiveKeys(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $config              = new Config([
            'section' => [
                'password' => 'sekret',
                'maskme'   => 'mask-value',
                'dropme'   => 'drop-value',
                'plain'    => 'keepme',
            ],
        ]);
        $app = $this->applicationWithServices(new Manager(), [
            'request' => new Request(),
            'config'  => $config,
        ]);

        (new Provider($app, [
            'env'    => ['var' => self::ENV_VAR],
            'redact' => ['mask' => ['maskme'], 'hidden' => ['dropme']],
        ]))->boot();

        $panel = $this->bootedBar()->collect()['data']['config']['panel'];
        if (!is_array($panel)) {
            $this->fail('The config panel should be an array.');
        }

        $this->assertArrayHasKey('section.plain', $panel);
        $this->assertSame('keepme', $panel['section.plain']);
        $this->assertSame('***', $panel['section.password']);
        $this->assertSame('***', $panel['section.maskme']);
        $this->assertArrayNotHasKey('section.dropme', $panel);
    }

    public function testCustomBlockedListReplacesDefault(): void
    {
        $provider = new Provider(
            $this->application(new Manager()),
            ['env' => ['var' => self::ENV_VAR, 'blocked' => ['staging']]]
        );

        $_ENV[self::ENV_VAR] = 'staging';
        $this->assertFalse($provider->isAllowed());

        $_ENV[self::ENV_VAR] = 'production';
        $this->assertTrue($provider->isAllowed());
    }

    public function testDefaultBootRegistersEveryCollector(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $this->provider($this->application(new Manager()))->boot();

        $this->assertSame(
            [
                'version',
                'messages',
                'logger',
                'exceptions',
                'time',
                'memory',
                'database',
                'view',
                'route',
                'cache',
                'request',
                'config',
                'session',
            ],
            array_keys($this->bootedBar()->getCollectors())
        );
    }

    public function testDiagnosticHeaderIsSetByDefault(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $em                  = new Manager();
        $app                 = $this->application($em);

        $this->provider($app)->boot();

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');
        $em->fire('application:beforeSendResponse', $app, $response);

        $this->assertTrue($response->getHeaders()->has('X-Debug-Bar'));
    }

    public function testDisabledBootsNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $app                 = $this->application(new Manager());

        (new Provider($app, ['env' => ['var' => self::ENV_VAR], 'enabled' => false]))->boot();

        $this->assertNull(Debug::getBar());
    }

    public function testDisabledHistoryCollectorDoesNotValidateOrRegisterStorage(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $app                 = $this->applicationWithServices(new Manager(), [
            'request'  => new Request(),
            'response' => new Response(),
        ]);

        (new Provider($app, [
            'env'        => ['var' => self::ENV_VAR],
            'collectors' => ['history' => false],
            'history'    => ['enabled' => true],
        ]))->boot();

        /** @var DiInterface $container */
        $container = $app->getDI();
        $this->assertFalse($container->has(HistoryController::class));
        $this->assertFalse($this->bootedBar()->hasCollector('history'));
    }

    public function testDisablingCollectorsRemovesThem(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $app                 = $this->application(new Manager());

        (new Provider($app, [
            'env'        => ['var' => self::ENV_VAR],
            'collectors' => ['version' => false, 'config' => false],
        ]))->boot();

        $bar = $this->bootedBar();
        $this->assertFalse($bar->hasCollector('version'));
        $this->assertFalse($bar->hasCollector('config'));
        $this->assertTrue($bar->hasCollector('session'));
    }

    public function testEnvironmentResolvesFromGetenv(): void
    {
        putenv(self::GETENV_VAR . '=dev');

        try {
            $provider = new Provider(
                $this->application(new Manager()),
                ['env' => ['var' => self::GETENV_VAR]]
            );

            $this->assertTrue($provider->isAllowed());
        } finally {
            putenv(self::GETENV_VAR);
        }
    }

    public function testHeadersDisabledSuppressesDiagnosticHeader(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $em                  = new Manager();
        $app                 = $this->application($em);

        (new Provider($app, ['env' => ['var' => self::ENV_VAR], 'headers' => false]))->boot();

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');
        $em->fire('application:beforeSendResponse', $app, $response);

        $this->assertFalse($response->getHeaders()->has('X-Debug-Bar'));
    }

    public function testHistoryDoesNotResolveResponseServicesAtBoot(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $serviceSets = [
            ['request' => new stdClass(), 'response' => new Response(), 'router' => new Router(false)],
            ['request' => new Request(), 'response' => new stdClass(), 'router' => new Router(false)],
            ['request' => new Request(), 'router' => new Router(false)],
        ];

        foreach ($serviceSets as $services) {
            $app = $this->applicationWithServices(new Manager(), $services);

            (new Provider($app, [
                'env'     => ['var' => self::ENV_VAR],
                'history' => ['enabled' => true, 'path' => sys_get_temp_dir() . '/debugbar'],
            ]))->boot();

            /** @var DiInterface $container */
            $container = $app->getDI();
            $this->assertTrue($container->has(HistoryController::class));
            $this->assertTrue($this->bootedBar()->hasCollector('history'));
        }
    }

    public function testHistoryDoesNotResolveTheRouterService(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $app                 = $this->applicationWithServices(
            new Manager(),
            [
                'router'   => new stdClass(),
                'request'  => new Request(),
                'response' => new Response(),
                'url'      => new stdClass(),
            ]
        );

        (new Provider($app, [
            'env'     => ['var' => self::ENV_VAR],
            'history' => ['enabled' => true, 'path' => sys_get_temp_dir() . '/debugbar'],
        ]))->boot();

        /** @var DiInterface $container */
        $container = $app->getDI();
        $this->assertTrue($container->has(HistoryController::class));
        $this->assertTrue($this->bootedBar()->hasCollector('history'));
    }

    #[RunInSeparateProcess]
    #[DataProvider('historyEndpointRequests')]
    public function testHistoryEndpointDispatchesWithAGenericRoute(
        string $baseUri,
        string $method,
        int $status,
        string $expectedBody
    ): void {
        $_ENV[self::ENV_VAR]          = 'dev';
        $_SERVER['REQUEST_METHOD']    = $method;
        $_SERVER['REQUEST_URI']       = $baseUri . '_debugbar/open';
        $_SERVER['REMOTE_ADDR']       = '127.0.0.1';

        $container = new FactoryDefault();
        $url       = new Url();
        $url->setBaseUri($baseUri);
        $container->setShared('url', $url);
        $router = new Router(false);
        $router->add('/:controller/:action', ['controller' => 1, 'action' => 2]);
        $container->setShared('router', $router);
        $routes    = $router->getRoutes();
        $app       = new Application($container);
        $app->useImplicitView(false);
        $app->setEventsManager(new Manager());
        $applicationResponse = $container->getShared('response');

        (new Provider($app, [
            'env'     => ['var' => self::ENV_VAR],
            'history' => [
                'enabled' => true,
                'path'    => sys_get_temp_dir() . '/phalcon-debugbar-routing',
            ],
        ]))->boot();

        $this->assertSame($routes, $router->getRoutes());
        $panel = $this->bootedBar()->collect()['data']['history']['panel'];
        $this->assertIsArray($panel);
        $basePath = parse_url($baseUri, PHP_URL_PATH);
        $this->assertIsString($basePath);
        $this->assertSame($basePath . '_debugbar/open', $panel['url']);
        $response = $app->handle('/_debugbar/open');
        if (!$response instanceof ResponseInterface) {
            $this->fail('Expected the history route to return a response.');
        }

        $body = json_decode($response->getContent(), true);

        $this->assertSame($status, $response->getStatusCode());
        $this->assertNotSame($applicationResponse, $response);
        $this->assertSame(json_decode($expectedBody, true), $body);
        $this->assertSame('application/json; charset=UTF-8', $response->getHeaders()->get('Content-Type'));
    }

    public function testHistoryRegistersOnlyItsControllerAndLeavesRoutesUnchanged(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $em                  = new Manager();
        $router              = new Router(false);
        $app                 = $this->applicationWithServices($em, [
            'request'  => new Request(),
            'response' => new Response(),
            'router'   => $router,
        ]);

        (new Provider($app, [
            'env'     => ['var' => self::ENV_VAR],
            'history' => ['enabled' => true, 'path' => sys_get_temp_dir() . '/debugbar'],
        ]))->boot();

        /** @var DiInterface $container */
        $container = $app->getDI();
        $this->assertTrue($container->has(HistoryController::class));
        $this->assertTrue($this->bootedBar()->hasCollector('history'));
        $this->assertTrue($this->bootedBar()->hasCollector('memory'));
        $this->assertSame([], $router->getRoutes());
        $this->assertFalse($container->has('debugbar.history'));
        $this->assertFalse($container->has('debugbar.access_gate'));
    }

    public function testHistoryUsesTheFinalUrlServiceRegisteredAfterBoot(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $container           = new FactoryDefault();
        $initialUrl          = new Url();
        $initialUrl->setBaseUri('/initial/');
        $container->setShared('url', $initialUrl);
        $app = new Application($container);
        $app->setEventsManager(new Manager());

        (new Provider($app, [
            'env'     => ['var' => self::ENV_VAR],
            'history' => ['enabled' => true, 'path' => sys_get_temp_dir() . '/debugbar'],
        ]))->boot();

        $finalUrl = new Url();
        $finalUrl->setBaseUri('http://localhost:8080/final/');
        $container->setShared('url', $finalUrl);
        $panel = $this->bootedBar()->collect()['data']['history']['panel'];

        $this->assertIsArray($panel);
        $this->assertSame('/final/_debugbar/open', $panel['url']);
    }

    public function testHistoryWithoutEventsManagerDoesNotRegisterAnUnreachableEndpoint(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $container           = new FactoryDefault();
        $app                 = new Application($container);

        (new Provider($app, [
            'env'     => ['var' => self::ENV_VAR],
            'history' => ['enabled' => true, 'path' => sys_get_temp_dir() . '/debugbar'],
        ]))->boot();

        $this->assertFalse($container->has(HistoryController::class));
        $this->assertFalse($this->bootedBar()->hasCollector('history'));
    }

    public function testIsAllowedTracksTheEnvironment(): void
    {
        $provider = $this->provider($this->application(new Manager()));

        $this->assertFalse($provider->isAllowed());

        $_ENV[self::ENV_VAR] = 'production';
        $this->assertFalse($provider->isAllowed());

        $_ENV[self::ENV_VAR] = 'dev';
        $this->assertTrue($provider->isAllowed());
    }

    public function testMultibyteEnvironmentIsLowercased(): void
    {
        $_ENV[self::ENV_VAR] = 'ÖFFENTLICH';

        $provider = new Provider(
            $this->application(new Manager()),
            ['env' => ['var' => self::ENV_VAR, 'blocked' => ['öffentlich']]]
        );

        $this->assertFalse($provider->isAllowed());
    }

    public function testNonceIsStampedOnInjectedTags(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $em                  = new Manager();
        $app                 = $this->application($em);

        (new Provider($app, [
            'env'    => ['var' => self::ENV_VAR],
            'assets' => ['nonce' => 'nonce-xyz-123'],
        ]))->boot();

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');
        $em->fire('application:beforeSendResponse', $app, $response);

        $this->assertStringContainsString('nonce="nonce-xyz-123"', $response->getContent());
    }

    public function testNonInterfaceConfigServiceIsIgnored(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $app                 = $this->applicationWithServices(
            new Manager(),
            ['config' => new stdClass()]
        );

        $this->provider($app)->boot();

        $this->assertSame([], $this->bootedBar()->collect()['data']['config']['panel']);
    }

    public function testNonInterfaceRequestServiceIsIgnored(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $app                 = $this->applicationWithServices(
            new Manager(),
            ['request' => new stdClass()]
        );

        $this->provider($app)->boot();

        $this->assertSame([], $this->bootedBar()->collect()['data']['request']['panel']);
    }

    public function testRedirectResponseIsNotInjected(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $em                  = new Manager();
        $app                 = $this->application($em);

        $this->provider($app)->boot();

        $response = new Response();
        $response->setContent('<html><body></body></html>');
        $response->setStatusCode(302);
        $em->fire('application:beforeSendResponse', $app, $response);

        $this->assertStringNotContainsString('phalcon-debugbar-data', $response->getContent());
    }

    public function testRequestCollectorSnapshotsTheRequest(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $this->provider($this->application(new Manager()))->boot();

        $panel = $this->bootedBar()->collect()['data']['request']['panel'];
        if (!is_array($panel)) {
            $this->fail('The request panel should be an array.');
        }

        $this->assertArrayHasKey('Method', $panel);
        $this->assertArrayHasKey('URI', $panel);
    }

    public function testStreamedCollectorsSubscribeToEvents(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $em                  = new Manager();
        $app                 = $this->application($em);

        $this->provider($app)->boot();

        $em->fire('cache:afterGet', $app, 'cache-key-42');

        $this->assertSame(1, $this->bootedBar()->collect()['data']['cache']['badge']);
    }

    public function testStrictBlockedEnvironmentThrows(): void
    {
        $_ENV[self::ENV_VAR] = 'production';
        $message             = '';

        $provider = new Provider(
            $this->application(new Manager()),
            ['env' => ['var' => self::ENV_VAR, 'strict' => true]]
        );

        try {
            $provider->boot();
        } catch (CannotUseInProduction $exception) {
            $message = $exception->getMessage();
        }

        $this->assertSame(
            'The debug bar cannot boot: the "' . self::ENV_VAR
            . '" environment is undefined or blocked.',
            $message
        );
        $this->assertNull(Debug::getBar());
    }

    private function application(Manager $em): Application
    {
        $di = new Di();
        $di->setShared('request', new Request());
        $di->setShared('config', new Config(['app' => ['name' => 'test']]));

        $app = new Application($di);
        $app->setEventsManager($em);

        return $app;
    }

    /**
     * @param array<string, object> $services
     */
    private function applicationWithServices(Manager $em, array $services): Application
    {
        $di = new Di();
        foreach ($services as $name => $service) {
            $di->setShared($name, $service);
        }

        $app = new Application($di);
        $app->setEventsManager($em);

        return $app;
    }

    private function bootedBar(): DebugBar
    {
        $bar = Debug::getBar();
        if (!$bar instanceof DebugBar) {
            $this->fail('Expected the debug bar to be booted.');
        }

        return $bar;
    }

    private function provider(Application $app): Provider
    {
        return new Provider($app, ['env' => ['var' => self::ENV_VAR]]);
    }
}
