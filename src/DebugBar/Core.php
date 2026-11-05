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
use Phalcon\DebugBar\Collector\CacheCollector;
use Phalcon\DebugBar\Collector\ConfigCollector;
use Phalcon\DebugBar\Collector\DatabaseCollector;
use Phalcon\DebugBar\Collector\ExceptionsCollector;
use Phalcon\DebugBar\Collector\HistoryCollector;
use Phalcon\DebugBar\Collector\LoggerCollector;
use Phalcon\DebugBar\Collector\MemoryCollector;
use Phalcon\DebugBar\Collector\MessagesCollector;
use Phalcon\DebugBar\Collector\RequestCollector;
use Phalcon\DebugBar\Collector\RouteCollector;
use Phalcon\DebugBar\Collector\SessionCollector;
use Phalcon\DebugBar\Collector\TimeCollector;
use Phalcon\DebugBar\Collector\VersionCollector;
use Phalcon\DebugBar\Collector\ViewCollector;
use Phalcon\DebugBar\Contracts\Collector;
use Phalcon\DebugBar\Contracts\History;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\History\HistoryCookie;
use Phalcon\DebugBar\History\HistoryEndpoint;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Events\ManagerInterface;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;

use function getenv;
use function in_array;
use function is_string;
use function mb_strtolower;

/**
 * Boots the debug bar with no framework application. All its inputs are optional:
 * an events manager, a request and a config source. An integration attaches the
 * listener from `boot()` to its own response event. A host with no events manager
 * calls `handle()` when it has the response.
 *
 * @phpstan-import-type provider_config from DebugBarTypes
 */
final class Core
{
    private AccessGate $accessGate;

    /**
     * @var list<string>
     */
    private array $blocked;

    /**
     * @var array<string, bool>
     */
    private array $collectorsConfig;

    private bool $enabled;

    private string $envVar;

    private bool $headers;

    private ?ResponseListener $listener = null;

    private ?string $nonce;

    private Redactor $redactor;

    private bool $strict;

    /**
     * @param provider_config $config
     */
    public function __construct(array $config = [])
    {
        $env    = $config['env'] ?? [];
        $assets = $config['assets'] ?? [];
        $access = $config['access'] ?? [];
        $redact = $config['redact'] ?? [];

        $this->envVar           = $env['var'] ?? 'APP_ENV';
        $this->blocked          = $env['blocked'] ?? ['production', 'prod'];
        $this->strict           = $env['strict'] ?? false;
        $this->enabled          = $config['enabled'] ?? true;
        $this->nonce            = $assets['nonce'] ?? null;
        $this->accessGate       = new AccessGate($access['allow_ips'] ?? [], $access['callback'] ?? null);
        $this->collectorsConfig = $config['collectors'] ?? [];
        $this->headers          = $config['headers'] ?? true;
        $this->redactor         = new Redactor(
            [...Redactor::DEFAULT_KEYS, ...($redact['mask'] ?? [])],
            $redact['hidden'] ?? []
        );
    }

    /**
     * Creates the bar with its enabled collectors, points the `Debug` facade at
     * it and subscribes `ServiceBridge`. It returns the listener, or null when the
     * bar cannot boot.
     *
     * @throws CannotUseInProduction When `env.strict` is set and the environment is blocked or undefined.
     */
    public function boot(
        ?ManagerInterface $eventsManager = null,
        ?RequestInterface $request = null,
        ?ConfigInterface $config = null,
        ?History $history = null,
        ?HistoryEndpoint $historyEndpoint = null,
        ?HistoryCookie $historyCookie = null
    ): ?ResponseListener {
        if (true !== $this->canBoot()) {
            return null;
        }

        $bar = new DebugBar($eventsManager);
        foreach ($this->buildCollectors($request, $config) as $collector) {
            $bar->addCollector($collector);
        }
        if (null !== $historyEndpoint) {
            $bar->addCollector(new HistoryCollector($historyEndpoint));
        }

        Debug::setBar($bar);

        if (null !== $eventsManager) {
            (new ServiceBridge($bar))->subscribe($eventsManager);
        }

        $this->listener = new ResponseListener(
            $bar,
            new Renderer(),
            new Injector(),
            $this->accessGate,
            $request,
            new BarOptions($this->headers, $this->nonce),
            $history,
            $historyEndpoint,
            $historyCookie
        );

        return $this->listener;
    }

    /**
     * Whether the environment and the config permit the bar to boot.
     *
     * @throws CannotUseInProduction When `env.strict` is set and the environment is blocked or undefined.
     */
    public function canBoot(): bool
    {
        if (true !== $this->isAllowed()) {
            if (true === $this->strict) {
                throw new CannotUseInProduction(
                    'The debug bar cannot boot: the "' . $this->envVar
                    . '" environment is undefined or blocked.'
                );
            }

            return false;
        }

        return $this->enabled;
    }

    public function getAccessGate(): AccessGate
    {
        return $this->accessGate;
    }

    /**
     * Runs the response step. It does nothing when the bar did not boot.
     */
    public function handle(ResponseInterface $response): void
    {
        $this->listener?->handle($response);
    }

    /**
     * Whether the resolved environment permits the bar (present and not blocked).
     */
    public function isAllowed(): bool
    {
        $value = $this->resolveEnv();
        if ('' === $value) {
            return false;
        }

        return !in_array(mb_strtolower($value), $this->blocked, true);
    }

    public function isCollectorEnabled(string $name): bool
    {
        return $this->collectorsConfig[$name] ?? true;
    }

    /**
     * Builds the enabled collectors (per the config map; all on by default).
     *
     * @return list<Collector>
     */
    private function buildCollectors(?RequestInterface $request, ?ConfigInterface $config): array
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

        if ($this->isCollectorEnabled(MemoryCollector::NAME)) {
            $collectors[] = new MemoryCollector();
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

        if ($this->isCollectorEnabled(ConfigCollector::NAME)) {
            $collectors[] = new ConfigCollector($config, $this->redactor);
        }

        if ($this->isCollectorEnabled(SessionCollector::NAME)) {
            $collectors[] = new SessionCollector($this->redactor);
        }

        return $collectors;
    }

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
}
