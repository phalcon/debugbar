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
use Phalcon\Events\EventInterface;
use Phalcon\Events\ManagerInterface;

use function array_pop;
use function count;
use function is_string;
use function microtime;
use function round;

/**
 * Records rendered views by subscribing to `view:beforeRenderView`/
 * `view:afterRenderView`. The path is the event data on the "before" event, so
 * nothing is resolved from the container. A stack pairs each "before" with its
 * matching "after", so nested partials are timed correctly.
 */
final class ViewCollector extends AbstractCollector implements Subscriber
{
    public const NAME = 'view';

    /**
     * @var string
     */
    protected string $icon = 'icon-view';

    /**
     * @var string
     */
    protected string $label = 'View';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var list<array{path: string, start: float}>
     */
    private array $pending = [];

    /**
     * @var list<array{path: string, time: float}>
     */
    private array $rendered = [];

    /**
     * @return array{panel: list<array{label: string, message: string}>, badge: scalar|null}
     */
    public function collect(): array
    {
        $rows = [];
        foreach ($this->rendered as $view) {
            $rows[] = [
                'label'   => round($view['time'] * 1000, 2) . 'ms',
                'message' => $view['path'],
            ];
        }

        return [
            'panel' => $rows,
            'badge' => count($this->rendered),
        ];
    }

    /**
     * @param ManagerInterface $eventsManager
     *
     * @return void
     */
    public function subscribe(ManagerInterface $eventsManager): void
    {
        $eventsManager->attach(
            'view:beforeRenderView',
            function (EventInterface $event, mixed $view, mixed $path): void {
                $this->pending[] = [
                    'path'  => is_string($path) ? $path : '',
                    'start' => microtime(true),
                ];
            }
        );

        $eventsManager->attach(
            'view:afterRenderView',
            function (): void {
                $entry = array_pop($this->pending);
                if (null === $entry) {
                    return;
                }

                $this->rendered[] = [
                    'path' => $entry['path'],
                    'time' => microtime(true) - $entry['start'],
                ];
            }
        );
    }
}
