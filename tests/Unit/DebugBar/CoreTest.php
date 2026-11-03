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

use Phalcon\Config\Config;
use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\Core;
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\Di\Di;
use Phalcon\Events\Manager;
use Phalcon\Http\Request;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\BackupGlobals;

use function array_keys;
use function is_array;
use function sys_get_temp_dir;

#[BackupGlobals(true)]
final class CoreTest extends AbstractUnitTestCase
{
    private const ENV_VAR = 'DEBUGBAR_TEST_ENV';

    private const HTML    = '<html><body>hi</body></html>';

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

    public function testAccessGateFollowsTheConfig(): void
    {
        $core = $this->core(['access' => ['allow_ips' => ['203.0.113.1']]]);

        $this->assertTrue($core->getAccessGate()->allows('203.0.113.1'));
        $this->assertFalse($core->getAccessGate()->allows('203.0.113.9'));
    }

    public function testAccessGateStopsInjection(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $core                = $this->core(['access' => ['allow_ips' => ['203.0.113.1']]]);
        $core->boot(null, $this->request('203.0.113.9', '/orders'));
        $response            = $this->response();

        $core->handle($response);

        $this->assertSame(self::HTML, $response->getContent());
    }

    public function testBlockedEnvironmentBootsNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'production';
        $core                = $this->core();
        $response            = $this->response();

        $this->assertFalse($core->canBoot());
        $this->assertNull($core->boot());
        $this->assertNull(Debug::getBar());

        $core->handle($response);

        $this->assertSame(self::HTML, $response->getContent());
    }

    public function testBootInjectsWithNoFrameworkObjects(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $core                = $this->core();
        $response            = $this->response();

        $this->assertTrue($core->canBoot());

        $core->boot();
        $core->handle($response);

        $this->assertStringContainsString('phalcon-debugbar-data', $response->getContent());
        $this->assertInstanceOf(DebugBar::class, Debug::getBar());
    }

    public function testBootReturnsAListenerForAnyResponseEvent(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $manager             = new Manager();
        $listener            = $this->core()->boot($manager);
        if (null === $listener) {
            $this->fail('Expected a listener from the boot.');
        }

        $manager->attach('application:afterHandle', $listener);

        $response = $this->response();
        $manager->fire('application:afterHandle', $this, $response);

        $this->assertStringContainsString('phalcon-debugbar-data', $response->getContent());
    }

    public function testBootSubscribesCollectorsToTheEventsManager(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $manager             = new Manager();

        $this->core()->boot($manager);
        $manager->fire('cache:afterGet', $this, 'cache-key-42');

        $this->assertSame(1, $this->bootedBar()->collect()['data']['cache']['badge']);
    }

    public function testConfigSourceReachesTheCollectorRedacted(): void
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

        $this->core(['redact' => ['mask' => ['maskme'], 'hidden' => ['dropme']]])
            ->boot(null, null, $config);

        $panel = $this->bootedBar()->collect()['data']['config']['panel'];
        if (!is_array($panel)) {
            $this->fail('The config panel should be an array.');
        }

        $this->assertSame('keepme', $panel['section.plain']);
        $this->assertSame('***', $panel['section.password']);
        $this->assertSame('***', $panel['section.maskme']);
        $this->assertArrayNotHasKey('section.dropme', $panel);
    }

    public function testDefaultBootRegistersEveryCollectorInOrder(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $this->core()->boot();

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

    public function testDisabledBootsNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $core                = $this->core(['enabled' => false]);

        $this->assertFalse($core->canBoot());
        $this->assertNull($core->boot());
        $this->assertNull(Debug::getBar());
    }

    public function testDisabledCollectorIsNotCreated(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $core                = $this->core(['collectors' => ['version' => false]]);

        $core->boot();

        $this->assertFalse($core->isCollectorEnabled('version'));
        $this->assertTrue($core->isCollectorEnabled('session'));
        $this->assertFalse($this->bootedBar()->hasCollector('version'));
        $this->assertTrue($this->bootedBar()->hasCollector('session'));
    }

    public function testEnvironmentFollowsTheConfig(): void
    {
        $core = new Core(['env' => ['var' => self::ENV_VAR, 'blocked' => ['staging']]]);

        $this->assertFalse($core->isAllowed());

        $_ENV[self::ENV_VAR] = 'staging';
        $this->assertFalse($core->isAllowed());

        $_ENV[self::ENV_VAR] = 'production';
        $this->assertTrue($core->isAllowed());
    }

    public function testHandleBeforeBootDoesNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $response            = $this->response();

        $this->core()->handle($response);

        $this->assertSame(self::HTML, $response->getContent());
    }

    public function testHeadersOptionReachesTheListener(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $core = $this->core(['headers' => false]);
        $core->boot();
        $off = $this->response();
        $core->handle($off);

        $core = $this->core();
        $core->boot();
        $on = $this->response();
        $core->handle($on);

        $this->assertFalse($off->getHeaders()->has('X-Debug-Bar'));
        $this->assertTrue($on->getHeaders()->has('X-Debug-Bar'));
    }

    public function testHistoryEndpointRequestIsNotInjected(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $request             = $this->request('127.0.0.1', '/_debugbar/open');
        $core                = $this->core();
        $response            = $this->response();

        $core->boot(null, $request, null, null, $this->endpoint($request));
        $core->handle($response);

        $this->assertSame(self::HTML, $response->getContent());
    }

    public function testHistoryPartsAddTheCollectorAndRecordTheRequest(): void
    {
        unset($_COOKIE[HistoryCookie::NAME]);
        $_ENV[self::ENV_VAR] = 'dev';
        $request             = $this->request('127.0.0.1', '/orders');
        $history             = $this->createMock(History::class);
        $history->expects($this->once())->method('save');
        $queued              = false;
        $cookie              = new HistoryCookie(
            null,
            function () use (&$queued): bool {
                $queued = true;

                return true;
            }
        );
        $core                = $this->core();

        $core->boot(null, $request, null, $history, $this->endpoint($request), $cookie);
        $core->handle($this->response());

        $this->assertTrue($this->bootedBar()->hasCollector('history'));
        $this->assertTrue($queued);
    }

    public function testNonceOptionReachesTheListener(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $core                = $this->core(['assets' => ['nonce' => 'nonce-xyz-123']]);
        $response            = $this->response();

        $core->boot();
        $core->handle($response);

        $this->assertStringContainsString('nonce="nonce-xyz-123"', $response->getContent());
    }

    public function testNoSourcesLeaveTheirPanelsEmpty(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $this->core()->boot();

        $data = $this->bootedBar()->collect()['data'];

        $this->assertSame([], $data['config']['panel']);
        $this->assertSame([], $data['request']['panel']);
    }

    public function testRequestReachesTheCollector(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $this->core()->boot(null, new Request());

        $panel = $this->bootedBar()->collect()['data']['request']['panel'];
        if (!is_array($panel)) {
            $this->fail('The request panel should be an array.');
        }

        $this->assertArrayHasKey('Method', $panel);
        $this->assertArrayHasKey('URI', $panel);
    }

    public function testStrictBlockedEnvironmentThrows(): void
    {
        $_ENV[self::ENV_VAR] = 'production';
        $core                = new Core(['env' => ['var' => self::ENV_VAR, 'strict' => true]]);
        $message             = '';

        try {
            $core->boot();
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

    private function bootedBar(): DebugBar
    {
        $bar = Debug::getBar();
        if (!$bar instanceof DebugBar) {
            $this->fail('Expected the debug bar to be booted.');
        }

        return $bar;
    }

    /**
     * @param array{
     *     enabled?: bool,
     *     assets?: array{nonce?: string|null},
     *     access?: array{allow_ips?: list<string>},
     *     collectors?: array<string, bool>,
     *     headers?: bool,
     *     redact?: array{mask?: list<string>, hidden?: list<string>}
     * } $config
     */
    private function core(array $config = []): Core
    {
        return new Core(['env' => ['var' => self::ENV_VAR]] + $config);
    }

    private function endpoint(RequestInterface $request): HistoryEndpoint
    {
        $container = new Di();
        $container->setShared('request', $request);

        return new HistoryEndpoint(
            new HistoryOptions(true, '/_debugbar/open', sys_get_temp_dir() . '/unused'),
            $container
        );
    }

    private function request(string $clientIp, string $uri): RequestInterface
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getClientAddress')->willReturn($clientIp);
        $request->method('getURI')->willReturn($uri);
        $request->method('getMethod')->willReturn('GET');

        return $request;
    }

    private function response(): Response
    {
        $response = new Response();
        $response->setContent(self::HTML);

        return $response;
    }
}
