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
use Phalcon\Debug\Report\Superglobals;
use Phalcon\Support\Exception;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class SetSuperglobalsTest extends AbstractUnitTestCase
{
    public function testSetSuperglobalsIsUsedByRenderHtml(): void
    {
        $debug  = new Debug();
        $result = $debug->setSuperglobals(
            new Superglobals(
                ['INJECTED_REQUEST_KEY' => 'injected-req'],
                ['INJECTED_SERVER_KEY' => 'injected-srv']
            )
        );

        $this->assertInstanceOf(Debug::class, $result);

        $html = $debug->renderHtml(new Exception('boom'));

        // The injected snapshot is rendered instead of a read from the live
        // $_REQUEST/$_SERVER superglobals.
        $this->assertStringContainsString('INJECTED_REQUEST_KEY', $html);
        $this->assertStringContainsString('INJECTED_SERVER_KEY', $html);
    }
}
