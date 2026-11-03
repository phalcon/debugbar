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

namespace Phalcon\Tests\Support\DebugBar\ADR\Action\Page;

use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

/**
 * Returns an HTML response for `GET /page`.
 */
final class GetPage implements Action
{
    public const CONTENT = '<html><body>page</body></html>';

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        $response = new Response();
        $response->setContentType('text/html');
        $response->setContent(self::CONTENT);

        return $response;
    }
}
