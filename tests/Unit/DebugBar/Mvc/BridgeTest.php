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

namespace Phalcon\Tests\Unit\DebugBar\Mvc;

use Phalcon\DebugBar\Collector\ExceptionsCollector;
use Phalcon\DebugBar\Collector\RouteCollector;
use Phalcon\DebugBar\Contracts\Collector;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Mvc\Bridge;
use Phalcon\Events\Manager;
use Phalcon\Events\ManagerInterface;
use Phalcon\Mvc\RouterInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use RuntimeException;
use stdClass;

final class BridgeTest extends AbstractUnitTestCase
{
    public function testBarWithNoCollectorsAttachesNothing(): void
    {
        $manager = $this->createMock(ManagerInterface::class);
        $manager->expects($this->never())->method('attach');

        (new Bridge(new DebugBar()))->subscribe($manager);
    }

    public function testDispatchExceptionsAreAutoRecorded(): void
    {
        $collector     = new ExceptionsCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('dispatch:beforeException', $this, new RuntimeException('dispatched'));

        $envelope = $collector->collect();

        $this->assertSame(1, $envelope['badge']);
        $this->assertStringContainsString('dispatched', $envelope['panel'][0]['message']);
    }

    public function testNonRouterSourceIsIgnored(): void
    {
        $collector     = new RouteCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('router:matchedRoute', new stdClass(), null);

        $this->assertSame([], $collector->collect()['panel']);
    }

    public function testNonThrowableEventPayloadIsIgnored(): void
    {
        $collector     = new ExceptionsCollector();
        $eventsManager = $this->subscribed($collector);

        $eventsManager->fire('dispatch:beforeException', $this, 'not-a-throwable');

        $this->assertSame(0, $collector->collect()['badge']);
    }

    public function testRouteIsSnapshotFromTheRouter(): void
    {
        $collector     = new RouteCollector();
        $eventsManager = $this->subscribed($collector);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getModuleName')->willReturn('frontend');
        $router->method('getNamespaceName')->willReturn('App\Controllers');
        $router->method('getControllerName')->willReturn('session');
        $router->method('getActionName')->willReturn('login');
        $router->method('getParams')->willReturn(['id' => 5]);

        $eventsManager->fire('router:matchedRoute', $router, null);

        $panel = $collector->collect()['panel'];

        $this->assertSame('frontend', $panel['Module']);
        $this->assertArrayHasKey('Namespace', $panel);
        $this->assertSame('App\Controllers', $panel['Namespace']);
        $this->assertSame('session', $panel['Controller']);
        $this->assertSame('login', $panel['Action']);
        $this->assertSame('{"id":5}', $panel['Params']);
    }

    public function testRouterIsReadAtCollectTime(): void
    {
        $collector     = new RouteCollector();
        $eventsManager = $this->subscribed($collector);

        $action = 'before';
        $router = $this->createMock(RouterInterface::class);
        $router->method('getActionName')->willReturnCallback(
            function () use (&$action): string {
                return $action;
            }
        );

        $eventsManager->fire('router:matchedRoute', $router, null);
        $action = 'after';

        $this->assertSame('after', $collector->collect()['panel']['Action']);
    }

    private function subscribed(Collector $collector): Manager
    {
        $eventsManager = new Manager();

        (new Bridge((new DebugBar())->addCollector($collector)))->subscribe($eventsManager);

        return $eventsManager;
    }
}
