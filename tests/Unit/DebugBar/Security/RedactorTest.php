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

namespace Phalcon\Tests\Unit\DebugBar\Security;

use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class RedactorTest extends AbstractUnitTestCase
{
    public function testRedactsConfiguredKeysCaseInsensitively(): void
    {
        $redacted = (new Redactor())->redact([
            'user'     => 'nikos',
            'password' => 'hunter2',
            'Token'    => 'abc',
        ]);

        $this->assertSame('nikos', $redacted['user']);
        $this->assertSame('***', $redacted['password']);
        $this->assertSame('***', $redacted['Token']);
    }

    public function testRedactsNestedKeys(): void
    {
        $redacted = (new Redactor())->redact([
            'db' => ['host' => 'localhost', 'secret' => 'x'],
        ]);

        $this->assertSame(['host' => 'localhost', 'secret' => '***'], $redacted['db']);
    }

    public function testUsesCustomKeys(): void
    {
        $redacted = (new Redactor(['pin']))->redact([
            'pin'      => '1234',
            'password' => 'kept',
        ]);

        $this->assertSame('***', $redacted['pin']);
        $this->assertSame('kept', $redacted['password']);
    }
}
