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

use Phalcon\DebugBar\BarOptions;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\Injector;
use Phalcon\DebugBar\Renderer;
use Phalcon\DebugBar\ResponseListener;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Events\Event;
use Phalcon\Http\Response;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class ResponseListenerTest extends AbstractUnitTestCase
{
    public function testIgnoresNonResponsePayloads(): void
    {
        $listener = $this->listener(true);

        $listener($this->event(), null, 'not-a-response');

        $this->expectNotToPerformAssertions();
    }

    public function testInjectsWithoutRequestAndWithHeadersOff(): void
    {
        $listener = $this->listener(false);

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');

        $listener($this->event(), null, $response);

        $content = $response->getContent();
        $this->assertStringContainsString('phalcon-debugbar-data', $content);
        $this->assertFalse($response->getHeaders()->get('X-Debug-Bar'));
    }

    public function testDeniedByAccessGateIsNotInjected(): void
    {
        $listener = $this->listener(true, ['10.0.0.1']);

        $response = new Response();
        $response->setContent('<html><body>hi</body></html>');

        $listener($this->event(), null, $response);

        $this->assertStringNotContainsString('phalcon-debugbar-data', $response->getContent());
    }

    private function event(): Event
    {
        return new Event('application:beforeSendResponse', $this);
    }

    /**
     * @param bool         $headers
     * @param list<string> $allowedIps
     *
     * @return ResponseListener
     */
    private function listener(bool $headers, array $allowedIps = []): ResponseListener
    {
        return new ResponseListener(
            new DebugBar(),
            new Renderer(),
            new Injector(),
            new AccessGate($allowedIps, null),
            null,
            new BarOptions($headers, null)
        );
    }
}
