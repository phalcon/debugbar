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

namespace Phalcon\DebugBar\Collector;

use Phalcon\DebugBar\Contracts\Subscriber;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

use function count;
use function is_string;

/**
 * Records cache operations by subscribing to the `cache:after*` events. The key
 * is the event data, so it is read straight off the event — the `cache` service
 * is never resolved.
 *
 * @phpstan-import-type list_envelope from DebugBarTypes
 * @phpstan-import-type list_panel from DebugBarTypes
 */
final class CacheCollector extends AbstractCollector implements Subscriber
{
    public const NAME = 'cache';

    /**
     * @var string
     */
    protected string $icon = 'icon-cache';

    /**
     * @var string
     */
    protected string $label = 'Cache';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var list_panel
     */
    private array $operations = [];

    /**
     * @return list_envelope
     */
    public function collect(): array
    {
        return [
            'panel' => $this->operations,
            'badge' => count($this->operations),
        ];
    }

    /**
     * @param ManagerInterface $eventsManager
     *
     * @return void
     */
    public function subscribe(ManagerInterface $eventsManager): void
    {
        $events = [
            'cache:afterGet'    => 'Get',
            'cache:afterSet'    => 'Set',
            'cache:afterHas'    => 'Has',
            'cache:afterDelete' => 'Delete',
        ];

        foreach ($events as $eventName => $operation) {
            $eventsManager->attach(
                $eventName,
                function (EventInterface $event, mixed $cache, mixed $key) use ($operation): void {
                    $this->operations[] = [
                        'label'   => $operation,
                        'message' => is_string($key) ? $key : '',
                    ];
                }
            );
        }
    }
}
