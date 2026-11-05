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

use Phalcon\DebugBar\RequestContext;
use Phalcon\Http\RequestInterface;
use Phalcon\Talon\PHPUnit\AbstractUnitTestCase;

final class RequestContextTest extends AbstractUnitTestCase
{
    public function testClientAddressThatIsNotAStringIsNull(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getClientAddress')->willReturn(false);

        $this->assertNull(RequestContext::fromRequest($request)->clientIp);
    }

    public function testNoRequestHasNoClientAndNoFlags(): void
    {
        $context = RequestContext::fromRequest(null);

        $this->assertNull($context->clientIp);
        $this->assertFalse($context->isAjax);
        $this->assertFalse($context->isSecure);
    }

    public function testValuesComeFromTheRequest(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getClientAddress')->willReturn('203.0.113.10');
        $request->method('isAjax')->willReturn(true);
        $request->method('isSecure')->willReturn(true);

        $context = RequestContext::fromRequest($request);

        $this->assertSame('203.0.113.10', $context->clientIp);
        $this->assertTrue($context->isAjax);
        $this->assertTrue($context->isSecure);
    }
}
