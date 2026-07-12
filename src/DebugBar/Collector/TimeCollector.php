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

use function is_float;
use function microtime;
use function round;

/**
 * Reports the total request time plus any named `startMeasure`/`stopMeasure`
 * spans fed through the convenience API.
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
     * @var array<string, array{label: string, start: float, end: float|null}>
     */
    private array $measures = [];

    /**
     * @return array{panel: list<array{label: string, message: string}>, badge: scalar|null}
     */
    public function collect(): array
    {
        $start = $this->requestStart();
        $now   = microtime(true);

        $rows = [$this->row('Request', $now - $start)];
        foreach ($this->measures as $measure) {
            $rows[] = $this->row($measure['label'], ($measure['end'] ?? $now) - $measure['start']);
        }

        return [
            'panel' => $rows,
            'badge' => round(($now - $start) * 1000, 2) . 'ms',
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
            'start' => microtime(true),
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
            $this->measures[$name]['end'] = microtime(true);
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
     * @param float  $seconds
     *
     * @return array{label: string, message: string}
     */
    private function row(string $label, float $seconds): array
    {
        return [
            'label'   => $label,
            'message' => round($seconds * 1000, 2) . 'ms',
        ];
    }
}
