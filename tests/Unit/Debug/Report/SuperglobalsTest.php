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

namespace Phalcon\Tests\Unit\Debug\Report;

use Phalcon\Debug\Report\Superglobals;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;
use PHPUnit\Framework\Attributes\BackupGlobals;

#[BackupGlobals(true)]
final class SuperglobalsTest extends AbstractUnitTestCase
{
    public function testFromGlobalsReadsSuperglobals(): void
    {
        $_REQUEST['sg_request_key'] = 'req';
        $_SERVER['SG_SERVER_KEY']   = 'srv';

        $superglobals = Superglobals::fromGlobals();

        $this->assertArrayHasKey('sg_request_key', $superglobals->getRequest());
        $this->assertSame('req', $superglobals->getRequest()['sg_request_key']);
        $this->assertArrayHasKey('SG_SERVER_KEY', $superglobals->getServer());
        $this->assertSame('srv', $superglobals->getServer()['SG_SERVER_KEY']);
    }

    public function testGettersReturnConstructorValues(): void
    {
        $superglobals = new Superglobals(['a' => 1], ['b' => 2]);

        $this->assertSame(['a' => 1], $superglobals->getRequest());
        $this->assertSame(['b' => 2], $superglobals->getServer());
    }
}
