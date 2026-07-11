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
use Phalcon\DebugBar\Contracts\TimeAware;

use function count;

/**
 * A `time` collector that records the measures the convenience API delegates.
 */
final class RecordingTimeCollector extends AbstractCollector implements TimeAware
{
    public const NAME = 'time';

    /**
     * @var string
     */
    protected string $panel = 'list';

    /**
     * @var list<array{name: string, action: string, label: string|null}>
     */
    private array $measures = [];

    /**
     * @return array{panel: list<array{name: string, action: string, label: string|null}>, badge: scalar|null}
     */
    public function collect(): array
    {
        return [
            'panel' => $this->measures,
            'badge' => count($this->measures),
        ];
    }

    /**
     * @return list<array{name: string, action: string, label: string|null}>
     */
    public function getMeasures(): array
    {
        return $this->measures;
    }

    public function startMeasure(string $name, ?string $label = null): void
    {
        $this->measures[] = [
            'name'   => $name,
            'action' => 'start',
            'label'  => $label,
        ];
    }

    public function stopMeasure(string $name): void
    {
        $this->measures[] = [
            'name'   => $name,
            'action' => 'stop',
            'label'  => null,
        ];
    }
}
