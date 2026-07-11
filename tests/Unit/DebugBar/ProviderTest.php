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

use Phalcon\Container\ContainerFactory;
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\Provider;
use Phalcon\Di\Di;
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

    public function testBlockedEnvironmentThrowsAndBindsNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'production';
        $di                  = new Di();
        $thrown              = false;

        try {
            $this->provider()->register($di);
        } catch (CannotUseInProduction) {
            $thrown = true;
        }

        $this->assertTrue($thrown);
        $this->assertFalse($di->has('debugbar'));
        $this->assertNull(Debug::getBar());
    }

    public function testDisabledBindsNothing(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $di                  = new Di();

        (new Provider(['env' => ['var' => self::ENV_VAR], 'enabled' => false]))
            ->register($di);

        $this->assertFalse($di->has('debugbar'));
        $this->assertNull(Debug::getBar());
    }

    public function testIsAllowedTracksTheEnvironment(): void
    {
        $provider = $this->provider();

        $this->assertFalse($provider->isAllowed());

        $_ENV[self::ENV_VAR] = 'production';
        $this->assertFalse($provider->isAllowed());

        $_ENV[self::ENV_VAR] = 'dev';
        $this->assertTrue($provider->isAllowed());
    }

    public function testProvideOnContainerBindsTheService(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';

        $factory = new ContainerFactory();
        $factory->addProvider($this->provider());
        $container = $factory->newContainer();

        $this->assertInstanceOf(DebugBar::class, $container->get('debugbar'));
    }

    public function testRegisterOnDiBindsTheServiceAndFacade(): void
    {
        $_ENV[self::ENV_VAR] = 'dev';
        $di                  = new Di();

        $this->provider()->register($di);

        $this->assertTrue($di->has('debugbar'));
        $this->assertInstanceOf(DebugBar::class, $di->get('debugbar'));
        $this->assertSame($di->get('debugbar'), Debug::getBar());
    }

    private function provider(): Provider
    {
        return new Provider(['env' => ['var' => self::ENV_VAR]]);
    }
}
