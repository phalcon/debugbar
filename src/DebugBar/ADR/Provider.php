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
use Phalcon\DebugBar\Collector\HistoryCollector;
use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\Core;
use Phalcon\DebugBar\Debug;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryOptions;
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

    private ?HistoryCookie $historyCookie = null;

    private ?HistoryEndpoint $historyEndpoint = null;

    private HistoryOptions $historyOptions;

    /**
     * @param provider_config $config
     */
    public function __construct(array $config = [])
    {
        $this->core           = new Core($config);
        $this->historyOptions = HistoryOptions::fromConfig($config['history'] ?? []);
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
        $history       = $this->registerHistory($services, $eventsManager, $request);
        $listener      = $this->core->boot(
            $eventsManager,
            $request,
            null,
            $history,
            $this->historyEndpoint,
            $this->historyCookie
        );
        $bar           = Debug::getBar();

        if (null === $eventsManager || null === $listener || null === $bar) {
            return;
        }

        $eventsManager->attach('application:afterHandle', $listener);
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
    ): ?History {
        if (
            !$this->historyOptions->enabled
            || !$this->core->isCollectorEnabled(HistoryCollector::NAME)
        ) {
            return null;
        }

        $this->historyOptions->validate();

        if (null === $eventsManager || !$services->has(Router::class)) {
            return null;
        }

        $this->historyCookie = HistoryCookie::fromGlobals();
        $history             = new FilesystemHistory($this->historyOptions, null, $this->historyCookie);
        $history->validateStorage();
        $accessGate            = $this->core->getAccessGate();
        $baseUri               = $this->historyOptions->baseUri;
        $endpoint              = new HistoryEndpoint(
            $this->historyOptions,
            fn (): ?RequestInterface => $request,
            fn (): string => $baseUri
        );
        $this->historyEndpoint = $endpoint;

        $services->set(
            HistoryAction::class,
            fn (): HistoryAction => new HistoryAction($history, $accessGate)
        );
        $services->extend(
            Router::class,
            fn (Router $router): Router => new HistoryRouter($router, $endpoint)
        );

        return $history;
    }

    private function resolve(Collection $services, string $name): mixed
    {
        return $services->has($name) ? $services->get($name) : null;
    }
}
