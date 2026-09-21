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

namespace Phalcon\Tests\Unit\DebugBar\History;

use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\Di\Di;
use Phalcon\Di\FactoryDefault;
use Phalcon\Events\Event;
use Phalcon\Http\RequestInterface;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Url;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class HistoryEndpointTest extends AbstractUnitTestCase
{
    public function testInternalRequestsUseThePackageControllerAndDiscardRouteParameters(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getURI')->willReturn('/app1/_debugbar/open?id=entry');
        $endpoint   = $this->endpoint($request, '/app1/');
        $dispatcher = new Dispatcher();
        $dispatcher->setControllerSuffix('Handler');
        $dispatcher->setActionSuffix('Execute');
        $dispatcher->setParams(['unrelated']);

        $endpoint(new Event('beforeHandleRequest', $this), null, $dispatcher);

        $this->assertTrue($endpoint->matches());
        $this->assertSame(HistoryEndpoint::CONTROLLER_NAMESPACE, $dispatcher->getNamespaceName());
        $this->assertSame('Phalcon\\DebugBar\\Controllers\\HistoryController', $dispatcher->getControllerClass());
        $this->assertSame('open', $dispatcher->getActionName());
        $this->assertSame('Action', $dispatcher->getActionSuffix());
        $this->assertSame([], $dispatcher->getParams());
    }

    public function testMissingOptionalServicesUseTheRootPathAndDoNotMatch(): void
    {
        $endpoint = new HistoryEndpoint(new HistoryOptions(true), new Di());

        $this->assertSame('/', $endpoint->cookiePath());
        $this->assertSame('/_debugbar/open', $endpoint->url());
        $this->assertFalse($endpoint->matches());
    }

    public function testOrdinaryRequestsKeepTheirDispatcherConfiguration(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getURI')->willReturn('/app1/orders/open?next=/_debugbar/open');
        $endpoint   = $this->endpoint($request, 'http://localhost:8080/app1/');
        $dispatcher = new Dispatcher();
        $dispatcher->setNamespaceName('App');
        $dispatcher->setControllerName('orders');
        $dispatcher->setActionName('open');
        $dispatcher->setParams(['42']);

        $endpoint(new Event('beforeHandleRequest', $this), null, $dispatcher);

        $this->assertFalse($endpoint->matches());
        $this->assertSame('App', $dispatcher->getNamespaceName());
        $this->assertSame('orders', $dispatcher->getControllerName());
        $this->assertSame('open', $dispatcher->getActionName());
        $this->assertSame(['42'], $dispatcher->getParams());
    }

    public function testUnrelatedEventPayloadIsIgnored(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->never())->method('getURI');
        $endpoint = $this->endpoint($request);

        $endpoint(new Event('beforeHandleRequest', $this), null, null);
    }

    private function endpoint(RequestInterface $request, string $baseUri = '/'): HistoryEndpoint
    {
        $container = new FactoryDefault();
        $url       = new Url();
        $url->setBaseUri($baseUri);
        $container->setShared('request', $request);
        $container->setShared('url', $url);

        return new HistoryEndpoint(new HistoryOptions(true), $container);
    }
}
