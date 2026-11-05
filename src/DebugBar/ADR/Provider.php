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

use Phalcon\Contracts\ADR\Router\Router;
use Phalcon\Contracts\Container\Service\Collection;
use Phalcon\Contracts\Container\Service\Provider as ProviderContract;
use Phalcon\Contracts\Events\Manager as ManagerContract;
use Phalcon\Contracts\Http\AttributeRequest;
use Phalcon\Debug as DebugPage;
use Phalcon\DebugBar\Core;
use Phalcon\DebugBar\DebugBar;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\History\HistoryRecorder;
use Phalcon\DebugBar\ResponseListener;
use Phalcon\Events\ManagerInterface;
use Phalcon\Http\RequestInterface;

/**
 * Boots the debug bar in an ADR application. Register it after all other
 * providers: it resolves the events manager and the request, and the container
 * cannot change a service after that. The ADR application emits the response
 * after `application:afterHandle`, so the bar is injected on this event. `Bridge`
 * feeds the collectors from the other events of the application. When request
 * history is enabled, `HistoryRouter` sends the history URL to `HistoryAction`,
 * and the application routes do not change.
 *
 * @phpstan-import-type provider_config from DebugBarTypes
 */
final class Provider implements ProviderContract
{
    private Core $core;

    private bool | DebugPage $debugPage;

    /**
     * @param provider_config $config
     */
    public function __construct(array $config = [])
    {
        $this->core      = new Core($config);
        $this->debugPage = $config['debug_page'] ?? false;
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
        $request       = $request instanceof RequestInterface ? $request : null;
        $listener      = $this->core->boot(
            $eventsManager,
            $request,
            null,
            $this->registerHistory($services, $eventsManager, $request)
        );
        $bar           = $this->core->getBar();

        if (null === $eventsManager || null === $listener || null === $bar) {
            return;
        }

        $eventsManager->attach('application:afterHandle', $this->responseListener($listener, $bar, $request));
        (new Bridge($bar))->subscribe($eventsManager);
    }

    /**
     * Registers the Action and decorates the router. The router must not be
     * resolved yet: `extend()` throws for a resolved service.
     */
    private function registerHistory(
        Collection $services,
        ?ManagerInterface $eventsManager,
        ?RequestInterface $request
    ): ?HistoryRecorder {
        if (null === $eventsManager || !$services->has(Router::class)) {
            return null;
        }

        $recorder = $this->core->createHistory(fn (): ?RequestInterface => $request);
        if (null === $recorder) {
            return null;
        }

        $accessGate = $this->core->getAccessGate();
        $endpoint   = $recorder->endpoint();
        $history    = $recorder->history();

        $services->set(
            HistoryAction::class,
            fn (): HistoryAction => new HistoryAction($history, $accessGate)
        );
        $services->extend(
            Router::class,
            fn (Router $router): Router => new HistoryRouter($router, $endpoint)
        );

        return $recorder;
    }

    private function resolve(Collection $services, string $name): mixed
    {
        return $services->has($name) ? $services->get($name) : null;
    }

    private function responseListener(
        ResponseListener $listener,
        DebugBar $bar,
        ?RequestInterface $request
    ): DebugPageListener | ResponseListener {
        if (false === $this->debugPage) {
            return $listener;
        }

        return new DebugPageListener(
            $listener,
            $bar,
            true === $this->debugPage ? new DebugPage() : $this->debugPage,
            $this->core->getAccessGate(),
            $request
        );
    }
}
