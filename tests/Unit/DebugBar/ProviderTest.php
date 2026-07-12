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

    public function testBlockedEnvironmentThrows(): void
    {
        $_ENV[self::ENV_VAR] = 'production';
        $thrown              = false;

        try {
            $this->provider($this->application(new Manager()))->boot();
        } catch (CannotUseInProduction) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
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

    public function testDisabledBootsNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $app                 = $this->application(new Manager());

        (new Provider($app, ['env' => ['var' => self::ENV_VAR], 'enabled' => false]))->boot();

        $this->assertNull(Debug::getBar());
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

    private function application(Manager $em): Application
    {
        $di = new Di();
        $di->setShared('request', new Request());

        $app = new Application($di);
        $app->setEventsManager($em);

        return $app;
    }

    private function provider(Application $app): Provider
    {
        return new Provider($app, ['env' => ['var' => self::ENV_VAR]]);
    }
}
