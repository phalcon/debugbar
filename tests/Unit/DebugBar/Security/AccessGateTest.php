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

use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class AccessGateTest extends AbstractUnitTestCase
{
    public function testCallbackDecides(): void
    {
        $this->assertTrue((new AccessGate([], fn (): bool => true))->allows('127.0.0.1'));
        $this->assertFalse((new AccessGate([], fn (): bool => false))->allows('127.0.0.1'));
    }

    public function testEmptyAllowlistAllowsAnyClient(): void
    {
        $gate = new AccessGate();

        $this->assertTrue($gate->allows('203.0.113.9'));
        $this->assertTrue($gate->allows(null));
    }

    public function testIpAllowlistIsEnforced(): void
    {
        $gate = new AccessGate(['127.0.0.1']);

        $this->assertTrue($gate->allows('127.0.0.1'));
        $this->assertFalse($gate->allows('203.0.113.9'));
        $this->assertFalse($gate->allows(null));
    }

    public function testIpAndCallbackBothMustPass(): void
    {
        $this->assertFalse(
            (new AccessGate(['127.0.0.1'], fn (): bool => false))->allows('127.0.0.1')
        );
        $this->assertFalse(
            (new AccessGate(['127.0.0.1'], fn (): bool => true))->allows('203.0.113.9')
        );
    }
}
