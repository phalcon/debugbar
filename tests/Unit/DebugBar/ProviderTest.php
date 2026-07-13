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
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\Provider;
use Phalcon\Di\Di;
use Phalcon\Events\Manager;
use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phalcon\Mvc\Application;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\BackupGlobals;
use stdClass;

use function array_keys;
use function is_array;
use function putenv;

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
                'exceptions',
                'time',
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
     * @param Manager               $em
     * @param array<string, object> $services
     *
     * @return Application
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
