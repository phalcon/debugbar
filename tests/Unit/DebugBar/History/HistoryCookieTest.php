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
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

use function str_repeat;

final class HistoryCookieTest extends AbstractUnitTestCase
{
    public function testInvalidOrMissingCookieQueuesASecureBrowserIdentity(): void
    {
        $call   = [];
        $cookie = new HistoryCookie(
            'invalid',
            function (string $name, string $value, array $options) use (&$call): bool {
                $call = [$name, $value, $options];

                return true;
            }
        );

        $cookie->queue('/app/', true);

        $this->assertSame(HistoryCookie::NAME, $call[0]);
        $this->assertIsString($call[1]);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $call[1]);
        $this->assertSame(
            [
                'path'     => '/app/',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Lax',
            ],
            $call[2]
        );
    }

    public function testValidCookieIsReusedWithoutCallingTheCookieWriter(): void
    {
        $id     = str_repeat('a', 64);
        $called = false;
        $cookie = new HistoryCookie(
            $id,
            function () use (&$called): bool {
                $called = true;

                return true;
            }
        );

        $cookie->queue('/', false);

        $this->assertSame($id, $cookie->id());
        $this->assertFalse($called);
    }
}
