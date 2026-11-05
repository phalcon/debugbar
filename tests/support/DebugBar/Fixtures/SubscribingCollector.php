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

namespace Phalcon\Tests\Support\DebugBar\Fixtures;

use Phalcon\DebugBar\Collector\AbstractCollector;
use Phalcon\DebugBar\Contracts\Subscriber;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

use function count;
use function is_string;

/**
 * A custom collector that attaches its own listener, as an application does.
 */
final class SubscribingCollector extends AbstractCollector implements Subscriber
{
    public const EVENT = 'fixture:happened';

    public const NAME = 'subscribing_fixture';

    protected string $panel = 'list';

    /**
     * @var list<array{message: string}>
     */
    private array $rows = [];

    /**
     * @return array{panel: list<array{message: string}>, badge: int}
     */
    public function collect(): array
    {
        return [
            'panel' => $this->rows,
            'badge' => count($this->rows),
        ];
    }

    public function subscribe(ManagerInterface $eventsManager): void
    {
        $eventsManager->attach(
            self::EVENT,
            function (EventInterface $event, mixed $source, mixed $data): void {
                $this->rows[] = ['message' => is_string($data) ? $data : ''];
            }
        );
    }
}
