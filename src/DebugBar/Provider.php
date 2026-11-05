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

namespace Phalcon\DebugBar;

use Phalcon\Config\ConfigInterface;
use Phalcon\DebugBar\Collector\HistoryCollector;
use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\Controllers\HistoryController;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\Mvc\HistoryListener;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Di\DiInterface;
use Phalcon\Http\Request;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\Response;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Url\UrlInterface;

/**
 * Boots the debug bar against an MVC application. It reads the services from the
 * `Application`, boots `Core` with them, and attaches the response listener.
 * When request history is enabled it registers its controller in the DI and
 * selects it through an application listener, leaving the router unchanged.
 *
 * @phpstan-import-type provider_config from DebugBarTypes
 */
class Provider
{
    private Core $core;

    private ?HistoryCookie $historyCookie = null;

    private ?HistoryEndpoint $historyEndpoint = null;

    private HistoryOptions $historyOptions;

    /**
     * @param provider_config $config
     */
    public function __construct(private readonly Application $app, array $config = [])
    {
        $this->core           = new Core($config);
        $this->historyOptions = HistoryOptions::fromConfig($config['history'] ?? []);
    }

    /**
     * Boots `Core` with the services of the application and - when the app has an
     * EventsManager - attaches the response listener.
     *
     * @throws CannotUseInProduction When `env.strict` is set and the environment is blocked or undefined.
     */
    public function boot(): void
    {
        if (true !== $this->core->canBoot()) {
            return;
        }

        /** @var DiInterface $container */
        $container     = $this->app->getDI();
        $request       = $this->resolveRequest($container);
        $history       = $this->registerHistory($container, $this->core->getAccessGate());
        $eventsManager = $this->app->getEventsManager();
        $listener      = $this->core->boot(
            $eventsManager,
            $request,
            $this->resolveConfig($container),
            $history,
            $this->historyEndpoint,
            $this->historyCookie
        );

        if (null === $eventsManager || null === $listener) {
            return;
        }

        $eventsManager->attach('application:beforeSendResponse', $listener);
    }

    /**
     * Whether the resolved environment permits the bar (present and not blocked).
     */
    public function isAllowed(): bool
    {
        return $this->core->isAllowed();
    }

    /**
     * Wires the internal endpoint without reading or mutating the app router.
     */
    private function registerHistory(
        ?DiInterface $container,
        AccessGate $accessGate
    ): ?History {
        if (
            !$this->historyOptions->enabled
            || !$this->core->isCollectorEnabled(HistoryCollector::NAME)
        ) {
            return null;
        }

        $this->historyOptions->validate();

        $eventsManager = $this->app->getEventsManager();
        if (
            null === $container
            || null === $eventsManager
        ) {
            return null;
        }

        $this->historyCookie = HistoryCookie::fromGlobals();
        $history             = new FilesystemHistory($this->historyOptions, null, $this->historyCookie);
        $history->validateStorage();
        $request               = fn (): ?RequestInterface => $this->resolveSharedRequest($container);
        $this->historyEndpoint = new HistoryEndpoint(
            $this->historyOptions,
            $request,
            fn (): string => $this->resolveBaseUri($container)
        );
        // The DI binds this closure to the container, so `$this` is not the provider here.
        $container->setShared(
            HistoryController::class,
            function () use ($accessGate, $history, $request): HistoryController {
                return new HistoryController(
                    $history,
                    $accessGate,
                    $request() ?? new Request(),
                    new Response()
                );
            }
        );
        $eventsManager->attach('application:beforeHandleRequest', new HistoryListener($this->historyEndpoint));

        return $history;
    }

    private function resolveBaseUri(DiInterface $container): string
    {
        if (!$container->has('url')) {
            return '/';
        }

        $url = $container->getShared('url');

        return $url instanceof UrlInterface ? $url->getBaseUri() : '/';
    }

    private function resolveConfig(DiInterface $container): ?ConfigInterface
    {
        if (!$container->has('config')) {
            return null;
        }

        $config = $container->get('config');

        return $config instanceof ConfigInterface ? $config : null;
    }

    private function resolveRequest(?DiInterface $container): ?RequestInterface
    {
        if (null === $container || !$container->has('request')) {
            return null;
        }

        $request = $container->get('request');

        return $request instanceof RequestInterface ? $request : null;
    }

    private function resolveSharedRequest(DiInterface $container): ?RequestInterface
    {
        if (!$container->has('request')) {
            return null;
        }

        $request = $container->getShared('request');

        return $request instanceof RequestInterface ? $request : null;
    }
}
