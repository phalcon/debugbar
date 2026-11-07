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

use Phalcon\DebugBar\Json;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class JsonTest extends AbstractUnitTestCase
{
    public function testEncodeKeepsTheGivenFlags(): void
    {
        $this->assertSame(
            '{"path":"/é"}',
            Json::encode(['path' => '/é'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function testEncodeReplacesInvalidUtf8(): void
    {
        $this->assertSame('{"q":"\ufffd"}', Json::encode(['q' => "\xFF"]));
    }

    public function testEncodeReturnsFalseForAValueItCannotEncode(): void
    {
        $this->assertFalse(Json::encode(NAN));
    }
}
