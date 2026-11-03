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

namespace Phalcon\Tests\Support\DebugBar\ADR\Action\Data;

use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

/**
 * Returns a JSON response for `GET /data`.
 */
final class GetData implements Action
{
    public const CONTENT = '{"status":"ok"}';

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        $response = new Response();
        $response->setContentType('application/json');
        $response->setContent(self::CONTENT);

        return $response;
    }
}
