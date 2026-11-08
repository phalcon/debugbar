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

use Phalcon\DebugBar\DebugBarTypes;

use function array_pop;
use function count;
use function hrtime;

/**
 * Records rendered views. `startRender()` takes the path and `finishRender()` ends
 * the last render that started. A stack pairs each start with its finish, so nested
 * partials are timed correctly.
 *
 * @phpstan-import-type list_envelope from DebugBarTypes
 * @phpstan-type view_pending array{path: string, start: int|float}
 * @phpstan-type view_render array{path: string, time: int|float}
 */
final class ViewCollector extends AbstractCollector
{
    use FormatsDuration;
    use LimitsRows;

    public const NAME = 'view';

    protected string $label = 'View';

    protected string $panel = 'list';

    /**
     * @var list<view_pending>
     */
    private array $pending = [];

    /**
     * @var list<view_render>
     */
    private array $rendered = [];

    public function __construct(private readonly int $maxRows = self::DEFAULT_MAX_ROWS)
    {
    }

    /**
     * @return list_envelope
     */
    public function collect(): array
    {
        $rows = [];
        foreach ($this->rendered as $view) {
            $rows[] = [
                'label'   => $this->nanosToMs($view['time']),
                'message' => $view['path'],
            ];
        }

        if (0 < $this->droppedRows) {
            $rows[] = $this->droppedRow();
        }

        return [
            'panel' => $rows,
            'badge' => count($this->rendered) + $this->droppedRows,
        ];
    }

    /**
     * Records the last render that `startRender()` started. It does nothing when
     * no render is pending.
     */
    public function finishRender(): void
    {
        $entry = array_pop($this->pending);
        if (null === $entry || !$this->acceptsRow(count($this->rendered))) {
            return;
        }

        $this->rendered[] = [
            'path' => $entry['path'],
            'time' => hrtime(true) - $entry['start'],
        ];
    }

    public function startRender(string $path): void
    {
        $this->pending[] = [
            'path'  => $path,
            'start' => hrtime(true),
        ];
    }
}
