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

use Phalcon\ADR\Container\AdrProvider;
use Phalcon\Container\Container;
use Phalcon\Contracts\Events\Manager as ManagerContract;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\DebugBar\ADR\Provider;
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\Events\ManagerInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\ADR\Action\Data\GetData;
use Phalcon\Tests\Support\DebugBar\ADR\Action\Page\GetPage;
use Phalcon\Tests\Support\DebugBar\ADR\Front;
use Phalcon\Tests\Support\DebugBar\ADR\RecordingEmitter;
use PHPUnit\Framework\Attributes\BackupGlobals;

use function is_array;

#[BackupGlobals(true)]
final class ProviderTest extends AbstractUnitTestCase
{
    private const ENV_VAR = 'DEBUGBAR_TEST_ENV';

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

    public function testBlockedEnvironmentBootsNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'production';

        $response = $this->dispatch('/page');

        $this->assertSame(GetPage::CONTENT, $response->getContent());
        $this->assertFalse($response->getHeaders()->has('X-Debug-Bar'));
        $this->assertNull(Debug::getBar());
    }

    public function testBlockedEnvironmentResolvesNoService(): void
    {
        $_ENV[self::ENV_VAR] = 'production';

        $blocked = $this->container();

        $this->assertFalse($blocked->hasInstance(ManagerContract::class));
        $this->assertFalse($blocked->hasInstance(AttributeRequest::class));

        $_ENV[self::ENV_VAR] = 'dev';

        $allowed = $this->container();

        $this->assertTrue($allowed->hasInstance(ManagerContract::class));
        $this->assertTrue($allowed->hasInstance(AttributeRequest::class));
    }

    public function testBootsAndInjectsIntoAnHtmlResponse(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $response = $this->dispatch('/page');

        $this->assertStringContainsString('phalcon-debugbar-data', $response->getContent());
        $this->assertStringContainsString('page', $response->getContent());
        $this->assertTrue($response->getHeaders()->has('X-Debug-Bar'));
        $this->assertInstanceOf(DebugBar::class, Debug::getBar());
    }

    public function testCollectorsSubscribeToTheSharedEventsManager(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $manager = $this->container()->get(ManagerContract::class);
        if (!$manager instanceof ManagerInterface) {
            $this->fail('Expected the default events manager.');
        }

        $manager->fire('cache:afterGet', $this, 'cache-key-42');

        $this->assertSame(1, $this->bootedBar()->collect()['data']['cache']['badge']);
    }

    public function testContainerWithNoServicesAttachesNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $this->provider()->provide(new Container());

        $this->assertSame([], $this->bootedBar()->collect()['data']['request']['panel']);
    }

    public function testDoesNotInjectIntoAJsonResponse(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $response = $this->dispatch('/data');

        $this->assertSame(GetData::CONTENT, $response->getContent());
        $this->assertInstanceOf(DebugBar::class, Debug::getBar());
    }

    public function testErrorResponseReachesTheListener(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $response = $this->dispatch('/missing');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertTrue($response->getHeaders()->has('X-Debug-Bar'));
        $this->assertStringNotContainsString('phalcon-debugbar-data', $response->getContent());
    }

    public function testManagerOutsideTheEventsManagerInterfaceGetsNoListener(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $manager = $this->createMock(ManagerContract::class);
        $manager->expects($this->never())->method('attach');

        $container = new Container();
        $container->set(
            ManagerContract::class,
            function () use ($manager): ManagerContract {
                return $manager;
            }
        );

        $this->provider()->provide($container);

        $this->assertInstanceOf(DebugBar::class, Debug::getBar());
    }

    public function testRequestReachesTheCollector(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $this->dispatch('/page');

        $panel = $this->bootedBar()->collect()['data']['request']['panel'];
        if (!is_array($panel)) {
            $this->fail('The request panel should be an array.');
        }

        $this->assertSame('GET', $panel['Method']);
        $this->assertSame('/page', $panel['URI']);
    }

    public function testStrictBlockedEnvironmentThrows(): void
    {
        $_ENV[self::ENV_VAR] = 'production';

        $provider = new Provider(['env' => ['var' => self::ENV_VAR, 'strict' => true]]);

        $this->expectException(CannotUseInProduction::class);

        $provider->provide(new Container());
    }

    private function bootedBar(): DebugBar
    {
        $bar = Debug::getBar();
        if (!$bar instanceof DebugBar) {
            $this->fail('Expected the debug bar to be booted.');
        }

        return $bar;
    }

    private function container(): Container
    {
        $container = new Container();
        (new AdrProvider())->provide($container);
        $this->provider()->provide($container);

        return $container;
    }

    /**
     * Runs a full `GET` request through a real ADR front controller.
     */
    private function dispatch(string $uri): ResponseInterface
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = $uri;

        $emitter = new RecordingEmitter();
        $code    = (new Front($emitter, ['env' => ['var' => self::ENV_VAR]]))->run();

        $this->assertSame(0, $code);
        if (null === $emitter->response) {
            $this->fail('Expected the front controller to emit a response.');
        }

        return $emitter->response;
    }

    private function provider(): Provider
    {
        return new Provider(['env' => ['var' => self::ENV_VAR]]);
    }
}
