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

namespace Phalcon\Tests\Support\DebugBar\ADR\Action\Cache;

use Phalcon\Cache\Cache;
use Phalcon\Contracts\ADR\Action;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Http\Response;
use Phalcon\Http\ResponseInterface;

/**
 * Uses the cache and returns an HTML response for `GET /cache`.
 */
final class GetCache implements Action
{
    public const KEY = 'user_42';

    public function __construct(private readonly Cache $cache)
    {
    }

    public function __invoke(AttributeRequest $request): ResponseInterface
    {
        $this->cache->set(self::KEY, 42);
        $this->cache->has(self::KEY);
        $this->cache->get(self::KEY);
        $this->cache->delete(self::KEY);

        $response = new Response();
        $response->setContentType('text/html');
        $response->setContent('<html><body>cache</body></html>');

        return $response;
    }
}
