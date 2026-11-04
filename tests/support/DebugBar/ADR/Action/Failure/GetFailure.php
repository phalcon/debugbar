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

namespace Phalcon\Tests\Support\DebugBar\ADR\Action\Failure;

use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Http\ResponseInterface;
use RuntimeException;

/**
 * Throws an exception for `GET /failure`.
 */
final class GetFailure implements Action
{
    public const MESSAGE = 'the action failed';

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
