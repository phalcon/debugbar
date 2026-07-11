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

use Phalcon\Contracts\Container\Service\Collection;
use Phalcon\Contracts\Container\Service\Provider as ContainerServiceProvider;
use Phalcon\DebugBar\Binder\Container as ContainerBinder;
use Phalcon\DebugBar\Binder\Di as DiBinder;
use Phalcon\DebugBar\Contracts\Binder;
use Phalcon\DebugBar\Exceptions\CannotUseInProduction;
use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;

use function getenv;
use function in_array;
use function is_string;
use function mb_strtolower;

/**
 * Registers the debug bar on either container. Used with the classic DI via
 * `$di->register(new Provider($config))`, or with the newer container via
 * `$factory->addProvider(new Provider($config))`. Both delegate to one neutral
 * `run()` through a `Binder`.
 */
class Provider implements ServiceProviderInterface, ContainerServiceProvider
{
    public const SERVICE = 'debugbar';

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
     * @param array{env?: array{var?: string, blocked?: list<string>}, enabled?: bool} $config
     */
    public function __construct(array $config = [])
    {
        $env = $config['env'] ?? [];

        $this->envVar  = $env['var'] ?? 'APP_ENV';
        $this->blocked = $env['blocked'] ?? ['production', 'prod'];
        $this->enabled = $config['enabled'] ?? true;
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
     * @param Collection $services
     *
     * @return void
     * @throws CannotUseInProduction
     */
    public function provide(Collection $services): void
    {
        $this->run(new ContainerBinder($services));
    }

    /**
     * @param DiInterface $di
     *
     * @return void
     * @throws CannotUseInProduction
     */
    public function register(DiInterface $di): void
    {
        $this->run(new DiBinder($di));
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
     * @param Binder $binder
     *
     * @return void
     * @throws CannotUseInProduction
     */
    private function run(Binder $binder): void
    {
        if (true !== $this->isAllowed()) {
            throw new CannotUseInProduction(
                'The debug bar cannot be registered: the "' . $this->envVar
                . '" environment is undefined or blocked.'
            );
        }

        if (true !== $this->enabled) {
            return;
        }

        $bar = new DebugBar();

        // Collector registration (per the config map) arrives in Phase 3; the
        // response hook / injection is wired by the Output sub-plan (2c).

        $binder->set(self::SERVICE, fn (): DebugBar => $bar);

        Debug::setBar($bar);
    }
}
