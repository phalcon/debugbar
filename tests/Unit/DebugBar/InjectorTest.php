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

use Phalcon\DebugBar\Injector;
use Phalcon\Http\Response;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class InjectorTest extends AbstractUnitTestCase
{
    public function testInjectLeavesContentWithNoBodyTag(): void
    {
        $response = new Response();
        $response->setContent('plain');

        (new Injector())->inject($response, 'H', 'B');

        $this->assertSame('plain', $response->getContent());
    }

    public function testInjectSplicesBeforeTheLastBodyTag(): void
    {
        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');

        (new Injector())->inject($response, '<assets>', '<bar>');

        $this->assertSame('<html><body>hi<assets><bar></body></html>', $response->getContent());
    }

    public function testInjectUpdatesTheContentLength(): void
    {
        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');
        $response->setContentLength(28);

        (new Injector())->inject($response, 'H', 'B');

        $this->assertSame('30', $response->getHeaders()->get('Content-Length'));
    }

    public function testIsHtmlForAnHtmlContentType(): void
    {
        $response = new Response();
        $response->setContentType('text/html', 'UTF-8');

        $this->assertTrue((new Injector())->isHtml($response));
    }

    public function testIsHtmlForARedirectThatTheBarSkips(): void
    {
        $response = new Response();
        $response->setStatusCode(302);
        $response->setContentType('text/html');

        $this->assertTrue((new Injector())->isHtml($response));
    }

    public function testIsHtmlWhenThereIsNoContentType(): void
    {
        $this->assertTrue((new Injector())->isHtml(new Response()));
    }

    public function testIsNotHtmlForJson(): void
    {
        $response = new Response();
        $response->setContentType('application/json');

        $this->assertFalse((new Injector())->isHtml($response));
    }

    public function testShouldInjectForClientErrorStatus(): void
    {
        $response = new Response();
        $response->setStatusCode(400);
        $response->setContent('<html><body></body></html>');

        $this->assertTrue((new Injector())->shouldInject($response));
    }

    public function testShouldInjectForHtml(): void
    {
        $response = new Response();
        $response->setContent('<html><body></body></html>');

        $this->assertTrue((new Injector())->shouldInject($response));
    }

    public function testShouldInjectForOkStatus(): void
    {
        $response = new Response();
        $response->setStatusCode(200);
        $response->setContent('<html><body></body></html>');

        $this->assertTrue((new Injector())->shouldInject($response));
    }

    public function testShouldNotInjectAFileDownload(): void
    {
        $response = new Response();
        $response->setFileToSend(__FILE__);

        $this->assertFalse((new Injector())->shouldInject($response));
    }

    public function testShouldNotInjectAFragment(): void
    {
        $response = new Response();
        $response->setContent('<div>row</div>');

        $this->assertFalse((new Injector())->shouldInject($response));
    }

    public function testShouldNotInjectAnEmptyResponse(): void
    {
        $response = new Response();
        $response->setStatusCode(204);

        $this->assertFalse((new Injector())->shouldInject($response));
    }

    public function testShouldNotInjectCompressedContent(): void
    {
        $response = new Response();
        $response->setHeader('Content-Encoding', 'gzip');
        $response->setContent('<html><body></body></html>');

        $this->assertFalse((new Injector())->shouldInject($response));
    }

    public function testShouldNotInjectForAjax(): void
    {
        $this->assertFalse((new Injector())->shouldInject(new Response(), true));
    }

    public function testShouldNotInjectForJson(): void
    {
        $response = new Response();
        $response->setContentType('application/json');

        $this->assertFalse((new Injector())->shouldInject($response));
    }

    public function testShouldNotInjectForMultipleChoicesStatus(): void
    {
        $response = new Response();
        $response->setStatusCode(300);

        $this->assertFalse((new Injector())->shouldInject($response));
    }

    public function testShouldNotInjectForRedirect(): void
    {
        $response = new Response();
        $response->setStatusCode(302);

        $this->assertFalse((new Injector())->shouldInject($response));
    }

    public function testShouldNotInjectJsonWithALowercaseHeader(): void
    {
        $response = new Response();
        $response->setHeader('content-type', 'application/json');
        $response->setContent('{"id":1}');

        $this->assertFalse((new Injector())->shouldInject($response));
    }
}
