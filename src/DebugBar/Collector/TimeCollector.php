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

use Phalcon\DebugBar\Contracts\TimeAware;

use function hrtime;
use function is_float;
use function microtime;
use function round;

/**
 * Reports the total request time plus any named `startMeasure`/`stopMeasure`
 * spans fed through the convenience API.
 *
 * @phpstan-import-type list_envelope from \Phalcon\DebugBar\DebugBarTypes
 * @phpstan-import-type list_row from \Phalcon\DebugBar\DebugBarTypes
 */
final class TimeCollector extends AbstractCollector implements TimeAware
{
    public const NAME = 'time';

    /**
     * @var string
     */
    protected string $icon = 'icon-time';

    /**
     * @var string
     */
    protected string $label = 'Time';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var array<string, array{label: string, start: int|float, end: int|float|null}>
     */
    private array $measures = [];

    /**
     * @return list_envelope
     */
    public function collect(): array
    {
        $requestMs = (microtime(true) - $this->requestStart()) * 1000;
        $hrNow     = hrtime(true);

        $rows = [$this->row('Request', $requestMs)];
        foreach ($this->measures as $measure) {
            $rows[] = $this->row($measure['label'], (($measure['end'] ?? $hrNow) - $measure['start']) / 1e6);
        }

        return [
            'panel' => $rows,
            'badge' => round($requestMs, 2) . 'ms',
        ];
    }

    /**
     * @param string      $name
     * @param string|null $label
     *
     * @return void
     */
    public function startMeasure(string $name, ?string $label = null): void
    {
        $this->measures[$name] = [
            'label' => $label ?? $name,
            'start' => hrtime(true),
            'end'   => null,
        ];
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function stopMeasure(string $name): void
    {
        if (isset($this->measures[$name])) {
            $this->measures[$name]['end'] = hrtime(true);
        }
    }

    /**
     * @return float
     */
    private function requestStart(): float
    {
        $value = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        return is_float($value) ? $value : microtime(true);
    }

    /**
     * @param string $label
     * @param float  $milliseconds
     *
     * @return list_row
     */
    private function row(string $label, float $milliseconds): array
    {
        return [
            'label'   => $label,
            'message' => round($milliseconds, 2) . 'ms',
        ];
    }
}
