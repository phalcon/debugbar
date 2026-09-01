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

use Closure;
use Phalcon\Config\ConfigInterface;
use Phalcon\DebugBar\Collector\CacheCollector;
use Phalcon\DebugBar\Collector\ConfigCollector;
use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\DebugBar\Collector\ExceptionsCollector;
use Phalcon\DebugBar\Collector\HistoryCollector;
use Phalcon\DebugBar\Collector\LoggerCollector;
use Phalcon\DebugBar\Collector\MessagesCollector;
use Phalcon\DebugBar\Collector\RequestCollector;
use Phalcon\DebugBar\Collector\RouteCollector;
use Phalcon\DebugBar\Collector\SessionCollector;
use Phalcon\DebugBar\Collector\TimeCollector;
use Phalcon\DebugBar\Collector\VersionCollector;
use Phalcon\DebugBar\Collector\ViewCollector;
use Phalcon\DebugBar\Contracts\Collector;
use Phalcon\DebugBar\Contracts\Subscriber;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\History\FilesystemHistory;
use Phalcon\DebugBar\History\HistoryOptions;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Di\DiInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\RouterInterface;

use function getenv;
use function in_array;
use function is_string;
use function mb_strtolower;

/**
 * Boots the debug bar against an MVC application. Its whole coupling to the app
 * is: hold the `Application`, reach its EventsManager, and attach listeners.
 * When request history is enabled it additionally registers an internal route
 * and two private DI services used by its controller.
 *
 * @phpstan-import-type provider_config from DebugBarTypes
 */
class Provider
{
    public const ACCESS_GATE_SERVICE = 'debugbar.accessGate';
    public const HISTORY_SERVICE     = 'debugbar.history';

    /**
     * @var (Closure(): bool)|null
     */
    private ?Closure $accessCallback;

    /**
     * @var list<string>
     */
    private array $allowedIps;

    /**
     * @var list<string>
     */
    private array $blocked;

    /**
     * @var array<string, bool>
     */
    private array $collectorsConfig;

    /**
     * @var bool
     */
    private bool $enabled;

    /**
     * @var string
     */
    private string $envVar;

    /**
     * @var bool
     */
    private bool $headers;

    /**
     * @var HistoryOptions
     */
    private HistoryOptions $historyOptions;

    /**
     * @var string|null
     */
    private ?string $nonce;

    /**
     * @var Redactor
     */
    private Redactor $redactor;

    /**
     * @var bool
     */
    private bool $strict;

    /**
     * @param Application     $app
     * @param provider_config $config
     */
    public function __construct(private readonly Application $app, array $config = [])
    {
        $env    = $config['env'] ?? [];
        $assets = $config['assets'] ?? [];
        $access = $config['access'] ?? [];
        $redact = $config['redact'] ?? [];
        $history = $config['history'] ?? [];

        $this->envVar           = $env['var'] ?? 'APP_ENV';
        $this->blocked          = $env['blocked'] ?? ['production', 'prod'];
        $this->strict           = $env['strict'] ?? false;
        $this->enabled          = $config['enabled'] ?? true;
        $this->nonce            = $assets['nonce'] ?? null;
        $this->allowedIps       = $access['allow_ips'] ?? [];
        $this->accessCallback   = $access['callback'] ?? null;
        $this->collectorsConfig = $config['collectors'] ?? [];
        $this->headers          = $config['headers'] ?? true;
        $this->historyOptions   = new HistoryOptions(
            $history['enabled'] ?? false,
            $history['url'] ?? '/_debugbar/open',
            $history['path'] ?? '',
            $history['max_requests'] ?? 100,
            $history['ttl_seconds'] ?? 86400
        );
        $this->redactor         = new Redactor(
            [...Redactor::DEFAULT_KEYS, ...($redact['mask'] ?? [])],
            $redact['hidden'] ?? []
        );
    }

    /**
     * Creates the bar with its enabled collectors, points the `Debug` facade at
     * it, and - when the app has an EventsManager - subscribes the streamed
     * collectors and attaches the response listener.
     *
     * @return void
     * @throws CannotUseInProduction When `env.strict` is set and the environment is blocked or undefined.
     */
    public function boot(): void
    {
        if (true !== $this->isAllowed()) {
            if (true === $this->strict) {
                throw new CannotUseInProduction(
                    'The debug bar cannot boot: the "' . $this->envVar
                    . '" environment is undefined or blocked.'
                );
            }

            return;
        }

        if (true !== $this->enabled) {
            return;
        }

        $container  = $this->app->getDI();
        $request    = $this->resolveRequest($container);
        $accessGate = new AccessGate($this->allowedIps, $this->accessCallback);
        $history    = $this->registerHistory($container, $accessGate);

        $bar = new DebugBar();
        foreach ($this->buildCollectors($container, $request) as $collector) {
            $bar->addCollector($collector);
        }
        if (null !== $history) {
            $bar->addCollector(new HistoryCollector($this->historyOptions->url));
        }

        Debug::setBar($bar);

        $eventsManager = $this->app->getEventsManager();
        if (null === $eventsManager || null === $container) {
            return;
        }

        foreach ($bar->getCollectors() as $collector) {
            if ($collector instanceof Subscriber) {
                $collector->subscribe($eventsManager);
            }
        }

        $eventsManager->attach(
            'application:beforeSendResponse',
            new ResponseListener(
                $bar,
                new Renderer(),
                new Injector(),
                $accessGate,
                $request,
                new BarOptions($this->headers, $this->nonce),
                $history,
                null !== $history ? $this->historyOptions : null
            )
        );
    }

    /**
     * Whether the resolved environment permits the bar (present and not blocked).
     *
     * @return bool
     */
    public function isAllowed(): bool
    {
        $value = $this->resolveEnv();
        if ('' === $value) {
            return false;
        }

        return !in_array(mb_strtolower($value), $this->blocked, true);
    }

    /**
     * Builds the enabled collectors (per the config map; all on by default).
     *
     * @param DiInterface|null      $container
     * @param RequestInterface|null $request
     *
     * @return list<Collector>
     */
    private function buildCollectors(?DiInterface $container, ?RequestInterface $request): array
    {
        $collectors = [];

        if ($this->isCollectorEnabled(VersionCollector::NAME)) {
            $collectors[] = new VersionCollector();
        }

        if ($this->isCollectorEnabled(MessagesCollector::NAME)) {
            $collectors[] = new MessagesCollector();
        }

        if ($this->isCollectorEnabled(LoggerCollector::NAME)) {
            $collectors[] = new LoggerCollector();
        }

        if ($this->isCollectorEnabled(ExceptionsCollector::NAME)) {
            $collectors[] = new ExceptionsCollector();
        }

        if ($this->isCollectorEnabled(TimeCollector::NAME)) {
            $collectors[] = new TimeCollector();
        }

        if ($this->isCollectorEnabled(DatabaseCollector::NAME)) {
            $collectors[] = new DatabaseCollector();
        }

        if ($this->isCollectorEnabled(ViewCollector::NAME)) {
            $collectors[] = new ViewCollector();
        }

        if ($this->isCollectorEnabled(RouteCollector::NAME)) {
            $collectors[] = new RouteCollector();
        }

        if ($this->isCollectorEnabled(CacheCollector::NAME)) {
            $collectors[] = new CacheCollector();
        }

        if ($this->isCollectorEnabled(RequestCollector::NAME)) {
            $collectors[] = new RequestCollector($request, $this->redactor);
        }

        if (null !== $container && $this->isCollectorEnabled(ConfigCollector::NAME)) {
            $collectors[] = new ConfigCollector($this->resolveConfig($container), $this->redactor);
        }

        if ($this->isCollectorEnabled(SessionCollector::NAME)) {
            $collectors[] = new SessionCollector($this->redactor);
        }

        return $collectors;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    private function isCollectorEnabled(string $name): bool
    {
        return $this->collectorsConfig[$name] ?? true;
    }

    /**
     * Registers the internal history module and its MVC route. History stays
     * disabled when the app has no compatible container/router.
     *
     * @param DiInterface|null $container
     * @param AccessGate       $accessGate
     *
     * @return FilesystemHistory|null
     */
    private function registerHistory(?DiInterface $container, AccessGate $accessGate): ?FilesystemHistory
    {
        if (
            !$this->historyOptions->enabled
            || null === $container
            || !$container->has('router')
        ) {
            return null;
        }

        $router = $container->getShared('router');
        if (!$router instanceof RouterInterface) {
            return null;
        }

        $history = new FilesystemHistory($this->historyOptions);
        $container->setShared(self::HISTORY_SERVICE, $history);
        $container->setShared(self::ACCESS_GATE_SERVICE, $accessGate);

        $router->addGet(
            $this->historyOptions->url,
            [
                'namespace'  => 'Phalcon\\DebugBar\\Controllers',
                'controller' => 'openHandler',
                'action'     => 'index',
            ]
        )->setName('debugbar.openhandler');

        $router->addDelete(
            $this->historyOptions->url,
            [
                'namespace'  => 'Phalcon\\DebugBar\\Controllers',
                'controller' => 'openHandler',
                'action'     => 'clear',
            ]
        )->setName('debugbar.clearhistory');

        return $history;
    }

    /**
     * @param DiInterface $container
     *
     * @return ConfigInterface|null
     */
    private function resolveConfig(DiInterface $container): ?ConfigInterface
    {
        if (!$container->has('config')) {
            return null;
        }

        $config = $container->get('config');

        return $config instanceof ConfigInterface ? $config : null;
    }

    /**
     * @return string
     */
    private function resolveEnv(): string
    {
        $candidates = [
            getenv($this->envVar),
            $_ENV[$this->envVar] ?? null,
            $_SERVER[$this->envVar] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && '' !== $candidate) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param DiInterface|null $container
     *
     * @return RequestInterface|null
     */
    private function resolveRequest(?DiInterface $container): ?RequestInterface
    {
        if (null === $container || !$container->has('request')) {
            return null;
        }

        $request = $container->get('request');

        return $request instanceof RequestInterface ? $request : null;
    }
}
