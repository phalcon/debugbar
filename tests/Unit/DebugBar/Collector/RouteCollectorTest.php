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

namespace Phalcon\Tests\Unit\DebugBar\Collector;

use Phalcon\DebugBar\Collector\RouteCollector;
use Phalcon\Events\Manager;
use Phalcon\Mvc\RouterInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use Phalcon\Tests\Support\DebugBar\PanelContractTrait;
use stdClass;

final class RouteCollectorTest extends AbstractUnitTestCase
{
    use PanelContractTrait;

    public function testNameAndPanelContract(): void
    {
        $collector = new RouteCollector();

        $this->assertSame('route', $collector->getName());
        $this->assertPanelContract($collector);
    }

    public function testNonRouterSourceIsIgnored(): void
    {
        $collector     = new RouteCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

        $eventsManager->fire('router:matchedRoute', new stdClass(), null);

        $this->assertSame([], $collector->collect()['panel']);
    }

    public function testRouteClosureIsCalledAtCollectTime(): void
    {
        $collector = new RouteCollector();
        $action    = 'before';

        $collector->setRoute(
            function () use (&$action): array {
                return ['Action' => $action];
            }
        );
        $action = 'after';

        $this->assertSame(['Action' => 'after'], $collector->collect()['panel']);
    }

    public function testRouteIsSnapshotFromTheRouter(): void
    {
        $collector     = new RouteCollector();
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

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
        $eventsManager = new Manager();
        $collector->subscribe($eventsManager);

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

    public function testRouteValuesAreShownInThePanel(): void
    {
        $collector = new RouteCollector();
        $values    = ['Action' => 'App\Action\GetPage', 'Attributes' => '{"id":5}'];

        $collector->setRoute($values);

        $envelope = $collector->collect();

        $this->assertSame($values, $envelope['panel']);
        $this->assertNull($envelope['badge']);
    }
}
