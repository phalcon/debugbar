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

namespace Phalcon\Tests\Support\DebugBar\ADR;

use Phalcon\ADR\Application;
use Phalcon\ADR\ErrorResponder;
use Phalcon\ADR\Front\AbstractHttpFront;
use Phalcon\ADR\Responder\StatusMapper;
use Phalcon\ADR\Responder\ViewResponder;
use Phalcon\Cache\Adapter\Memory;
use Phalcon\Cache\Cache;
use Phalcon\Container\Container;
use Phalcon\Contracts\ADR\Application as ApplicationInterface;
use Phalcon\Contracts\ADR\Emitter\Emitter;
use Phalcon\Contracts\Events\Manager as ManagerContract;
use Phalcon\Contracts\Logger\Logger as LoggerContract;
use Phalcon\Contracts\View\Renderer;
use Phalcon\DataMapper\Pdo\Connection;
use Phalcon\DebugBar\ADR\Provider;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\Logger\ExceptionAdapter;
use Phalcon\Events\ManagerInterface;
use Phalcon\Logger\Logger;
use Phalcon\Mvc\View\Simple;
use Phalcon\Storage\SerializerFactory;

/**
 * An ADR front controller that registers the debug bar provider last, as an
 * application does. It routes to the Actions in this folder.
 *
 * @phpstan-import-type provider_config from DebugBarTypes
 */
final class Front extends AbstractHttpFront
{
    /**
     * @param provider_config $config
     */
    public function __construct(
        private readonly Emitter $emitter,
        private readonly array $config = [],
        private readonly bool $htmlErrors = false
    ) {
        parent::__construct(__DIR__);
    }

    protected function getApplication(Container $container): ApplicationInterface
    {
        return (new Application($container))
            ->setBaseNamespace(__NAMESPACE__ . '\\Action')
            ->setActionDirectory($this->projectRoot . '/Action');
    }

    protected function registerProviders(Container $container): void
    {
        parent::registerProviders($container);

        $emitter = $this->emitter;
        $container->set(
            Emitter::class,
            function () use ($emitter): Emitter {
                return $emitter;
            }
        );

        $container->set(
            Connection::class,
            function () use ($container): Connection {
                $connection = new Connection('sqlite::memory:');
                $this->giveEventsManager($container, $connection);

                return $connection;
            }
        );

        $container->set(
            Renderer::class,
            function () use ($container): Renderer {
                return $this->newRenderer($container);
            }
        );

        $container->set(
            Cache::class,
            function () use ($container): Cache {
                $cache = new Cache(new Memory(new SerializerFactory()));
                $this->giveEventsManager($container, $cache);

                return $cache;
            }
        );

        $container->set(
            LoggerContract::class,
            function (): LoggerContract {
                return $this->newLogger();
            }
        );

        if ($this->htmlErrors) {
            $container->set(
                ErrorResponder::class,
                function () use ($container): ErrorResponder {
                    return new ErrorResponder(
                        new ViewResponder($this->newRenderer($container), new StatusMapper(), 'error'),
                        $this->newLogger()
                    );
                }
            );
        }

        (new Provider($this->config))->provide($container);
    }

    private function giveEventsManager(Container $container, Cache | Connection | Simple $service): void
    {
        $eventsManager = $container->get(ManagerContract::class);
        if ($eventsManager instanceof ManagerInterface) {
            $service->setEventsManager($eventsManager);
        }
    }

    private function newLogger(): Logger
    {
        return new Logger('adr', ['debugbar' => new ExceptionAdapter()]);
    }

    private function newRenderer(Container $container): Simple
    {
        $view = new Simple();
        $view->setViewsDir($this->projectRoot . '/Views/');
        $this->giveEventsManager($container, $view);

        return $view;
    }
}
