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

namespace Phalcon\Tests\Unit\Debug;

use Phalcon\Debug;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use ReflectionProperty;

final class SetBlacklistTest extends AbstractUnitTestCase
{
    /**
     * @author Phalcon Team <team@phalcon.io>
     * @since  2026-07-13
     */
    public function testSupportDebugSetBlacklist(): void
    {
        $debug = new Debug();

        /**
         * The upper case multibyte characters (Ä) only get lower cased by
         * mb_strtolower(), never by strtolower(), so the resulting keys prove
         * the multibyte function is used and each entry maps to exactly 1.
         */
        $result = $debug->setBlacklist(
            [
                'request' => ['RÄQUEST'],
                'server'  => ['SÄRVER'],
            ]
        );

        $blacklist = $this->readBlacklist($debug);

        /** @var array<string, int> $request */
        $request = $blacklist['request'];
        /** @var array<string, int> $server */
        $server = $blacklist['server'];

        $this->assertInstanceOf(Debug::class, $result);

        $this->assertArrayHasKey('räquest', $request);
        $this->assertSame(1, $request['räquest']);

        $this->assertArrayHasKey('särver', $server);
        $this->assertSame(1, $server['särver']);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readBlacklist(Debug $debug): array
    {
        $property = new ReflectionProperty(Debug::class, 'blacklist');

        /** @var array<array-key, mixed> $value */
        $value = $property->getValue($debug);

        return $value;
    }
}
