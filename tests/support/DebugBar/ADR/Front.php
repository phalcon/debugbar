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
use Phalcon\ADR\Front\AbstractHttpFront;
use Phalcon\Container\Container;
use Phalcon\Contracts\ADR\Application as ApplicationInterface;
use Phalcon\Contracts\ADR\Emitter\Emitter;
use Phalcon\Contracts\Events\Manager as ManagerContract;
use Phalcon\DataMapper\Pdo\Connection;
use Phalcon\DebugBar\ADR\Provider;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\Events\ManagerInterface;

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
        private readonly array $config = []
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
                $connection    = new Connection('sqlite::memory:');
                $eventsManager = $container->get(ManagerContract::class);
                if ($eventsManager instanceof ManagerInterface) {
                    $connection->setEventsManager($eventsManager);
                }

                return $connection;
            }
        );

        (new Provider($this->config))->provide($container);
    }
}
