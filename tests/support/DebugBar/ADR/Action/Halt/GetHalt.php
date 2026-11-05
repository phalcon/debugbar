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

namespace Phalcon\Tests\Support\DebugBar\ADR\Action\Halt;

use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Debug;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

/**
 * Calls `Debug::halt()` for `GET /halt`.
 */
final class GetHalt implements Action
{
    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        (new Debug())->halt();

        return new Response();
    }
}
