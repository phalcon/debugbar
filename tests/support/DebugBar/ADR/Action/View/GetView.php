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

namespace Phalcon\Tests\Support\DebugBar\ADR\Action\View;

use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Contracts\View\Renderer;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

/**
 * Renders a template and returns it as an HTML response for `GET /view`.
 */
final class GetView implements Action
{
    public const CONTENT = 'rendered template';

    public const TEMPLATE = 'page';

    public function __construct(private readonly Renderer $renderer)
    {
    }

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        $response = new Response();
        $response->setContentType('text/html');
        $response->setContent($this->renderer->render(self::TEMPLATE));

        return $response;
    }
}
