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
use Phalcon\DebugBar\DebugBarTypes;

use function hrtime;
use function is_float;
use function microtime;

/**
 * Reports the total request time plus any named `startMeasure`/`stopMeasure`
 * spans fed through the convenience API.
 *
 * @phpstan-import-type list_envelope from DebugBarTypes
 * @phpstan-import-type list_row from DebugBarTypes
 * @phpstan-import-type time_measure from DebugBarTypes
 */
final class TimeCollector extends AbstractCollector implements TimeAware
{
    use FormatsDuration;

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
     * @var array<string, time_measure>
     */
    private array $measures = [];

    /**
     * @return list_envelope
     */
    public function collect(): array
    {
        $requestMs = (microtime(true) - $this->requestStart()) * 1000;
        $hrNow     = hrtime(true);

        $rows = [$this->row('Request', $this->msLabel($requestMs))];
        foreach ($this->measures as $measure) {
            $elapsed = ($measure['end'] ?? $hrNow) - $measure['start'];
            $rows[]  = $this->row($measure['label'], $this->nanosToMs($elapsed));
        }

        return [
            'panel' => $rows,
            'badge' => $this->msLabel($requestMs),
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
     * @param string $message
     *
     * @return list_row
     */
    private function row(string $label, string $message): array
    {
        return [
            'label'   => $label,
            'message' => $message,
        ];
    }
}
