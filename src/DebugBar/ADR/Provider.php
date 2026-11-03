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

namespace Phalcon\DebugBar\ADR;

use Phalcon\Contracts\Container\Service\Collection;
use Phalcon\Contracts\Container\Service\Provider as ProviderContract;
use Phalcon\Contracts\Events\Manager as ManagerContract;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\DebugBar\Core;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\Events\ManagerInterface;
use Phalcon\Http\RequestInterface;

/**
 * Boots the debug bar in an ADR application. Register it after all other
 * providers: it resolves the events manager and the request, and the container
 * cannot change a service after that. The ADR application emits the response
 * after `application:afterHandle`, so the bar is injected on this event.
 *
 * @phpstan-import-type provider_config from DebugBarTypes
 */
final class Provider implements ProviderContract
{
    private Core $core;

    /**
     * @param provider_config $config
     */
    public function __construct(array $config = [])
    {
        $this->core = new Core($config);
    }

    /**
     * @throws CannotUseInProduction When `env.strict` is set and the environment is blocked or undefined.
     */
    public function provide(Collection $services): void
    {
        if (true !== $this->core->canBoot()) {
            return;
        }

        $eventsManager = $this->resolve($services, ManagerContract::class);
        $eventsManager = $eventsManager instanceof ManagerInterface ? $eventsManager : null;
        $request       = $this->resolve($services, AttributeRequest::class);
        $listener      = $this->core->boot(
            $eventsManager,
            $request instanceof RequestInterface ? $request : null
        );

        if (null === $eventsManager || null === $listener) {
            return;
        }

        $eventsManager->attach('application:afterHandle', $listener);
    }

    private function resolve(Collection $services, string $name): mixed
    {
        return $services->has($name) ? $services->get($name) : null;
    }
}
