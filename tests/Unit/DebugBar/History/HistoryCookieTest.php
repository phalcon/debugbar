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

use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\Http\Response;
use Phalcon\Http\Response\Headers;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

use function array_key_first;
use function iterator_to_array;
use function str_repeat;

final class HistoryCookieTest extends AbstractUnitTestCase
{
    public function testInvalidOrMissingCookieQueuesANewBrowserIdentity(): void
    {
        $response = new Response();
        (new HistoryCookie('invalid'))->queue($response, '/app/');

        $responseHeaders = $response->getHeaders();
        $this->assertInstanceOf(Headers::class, $responseHeaders);
        $headers = iterator_to_array($responseHeaders->getIterator());
        $header  = array_key_first($headers);
        $this->assertIsString($header);
        $this->assertMatchesRegularExpression(
            '/^Set-Cookie: phalcon-debugbar-history=[a-f0-9]{64}; Path=\/app\/; HttpOnly; SameSite=Lax$/',
            $header
        );
    }

    public function testValidCookieIsReusedWithoutQueuingAResponseCookie(): void
    {
        $id       = str_repeat('a', 64);
        $cookie   = new HistoryCookie($id);
        $response = new Response();

        $cookie->queue($response, '/');

        $this->assertSame($id, $cookie->id());
        $responseHeaders = $response->getHeaders();
        $this->assertInstanceOf(Headers::class, $responseHeaders);
        $this->assertSame([], iterator_to_array($responseHeaders->getIterator()));
    }
}
