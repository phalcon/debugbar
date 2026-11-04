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

namespace Phalcon\Tests\Support\DebugBar\ADR\Action\Queries;

use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\DataMapper\Pdo\Connection;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

/**
 * Runs a query and returns an HTML response for `GET /queries`.
 */
final class GetQueries implements Action
{
    public const STATEMENT = 'SELECT :value AS answer';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        $this->connection->fetchAll(self::STATEMENT, ['value' => 42]);

        $response = new Response();
        $response->setContentType('text/html');
        $response->setContent('<html><body>queries</body></html>');

        return $response;
    }
}
