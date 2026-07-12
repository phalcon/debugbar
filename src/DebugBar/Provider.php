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

use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\DebugBar\Security\AccessGate;
use Phalcon\Mvc\Application;

use function getenv;
use function in_array;
use function is_string;
use function mb_strtolower;

/**
 * Boots the debug bar against an MVC application. Its whole coupling to the app
 * is: hold the `Application`, reach its EventsManager, and attach listeners.
 * There is no DI service to register and no container-specific wiring — the
 * app hands over its container and event bus.
 */
class Provider
{
    /**
     * @var (callable(): bool)|null
     */
    private $accessCallback;

    /**
     * @var list<string>
     */
    private array $allowedIps;

    /**
     * @var Application
     */
    private Application $app;

    /**
     * @var string
     */
    private string $assetUri;

    /**
     * @var list<string>
     */
    private array $blocked;

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
     * @var string|null
     */
    private ?string $nonce;

    /**
     * @param Application $app
     * @param array{
     *     env?: array{var?: string, blocked?: list<string>},
     *     enabled?: bool,
     *     assets?: array{uri?: string, nonce?: string|null},
     *     access?: array{allow_ips?: list<string>, callback?: (callable(): bool)|null},
     *     headers?: bool
     * } $config
     */
    public function __construct(Application $app, array $config = [])
    {
        $this->app = $app;

        $env    = $config['env'] ?? [];
        $assets = $config['assets'] ?? [];
        $access = $config['access'] ?? [];

        $this->envVar         = $env['var'] ?? 'APP_ENV';
        $this->blocked        = $env['blocked'] ?? ['production', 'prod'];
        $this->enabled        = $config['enabled'] ?? true;
        $this->assetUri       = $assets['uri'] ?? 'https://assets.phalcon.io/debug/6.0.x/';
        $this->nonce          = $assets['nonce'] ?? null;
        $this->allowedIps     = $access['allow_ips'] ?? [];
        $this->accessCallback = $access['callback'] ?? null;
        $this->headers        = $config['headers'] ?? true;
    }

    /**
     * Creates the bar, points the `Debug` facade at it, and — when the app has
     * an EventsManager — attaches the response listener to it.
     *
     * @return void
     * @throws CannotUseInProduction
     */
    public function boot(): void
    {
        if (true !== $this->isAllowed()) {
            throw new CannotUseInProduction(
                'The debug bar cannot boot: the "' . $this->envVar
                . '" environment is undefined or blocked.'
            );
        }

        if (true !== $this->enabled) {
            return;
        }

        $bar = new DebugBar();

        // Enabled collectors (per the config map) are added and subscribed here
        // in Phase 3; today the bar boots empty.

        Debug::setBar($bar);

        $eventsManager = $this->app->getEventsManager();
        $container     = $this->app->getDI();
        if (null === $eventsManager || null === $container) {
            return;
        }

        $eventsManager->attach(
            'application:beforeSendResponse',
            new ResponseListener(
                $bar,
                new Renderer(),
                new Injector(),
                new AccessGate($this->allowedIps, $this->accessCallback),
                $container,
                $this->assetUri,
                $this->nonce,
                $this->headers
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
}
