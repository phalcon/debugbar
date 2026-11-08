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

namespace Phalcon\Tests\Unit\Debug\Contracts;

use Phalcon\Debug\Contracts\DebugThrowable;
use Phalcon\Debug\Exceptions\Exception;
use Phalcon\Debug\Exceptions\RequestHalted;
use Phalcon\Debug\Exceptions\RuntimeWarning;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Throwable;

final class DebugThrowableTest extends AbstractUnitTestCase
{
    /**
     * @return array<string, array{0: Throwable}>
     */
    public static function throwableProvider(): array
    {
        return [
            'Exception'      => [new Exception('failed')],
            'RequestHalted'  => [new RequestHalted()],
            'RuntimeWarning' => [new RuntimeWarning('warning')],
        ];
    }

    #[DataProvider('throwableProvider')]
    public function testEachDebugPageExceptionIsADebugThrowable(Throwable $throwable): void
    {
        $this->assertInstanceOf(DebugThrowable::class, $throwable);
    }
}
