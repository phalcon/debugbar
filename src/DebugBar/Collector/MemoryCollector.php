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

use function count;
use function memory_get_peak_usage;
use function memory_get_usage;
use function round;

/**
 * Reports the memory currently used by the PHP request and its peak.
 *
 * @phpstan-import-type grid_envelope from DebugBarTypes
 */
final class MemoryCollector extends AbstractCollector
{
    public const NAME = 'memory';

    protected string $icon = 'icon-memory';

    protected string $label = 'Memory';

    protected string $panel = 'grid';

    /**
     * @return grid_envelope
     */
    public function collect(): array
    {
        $current = $this->formatBytes(memory_get_usage(false));
        $peak    = $this->formatBytes(memory_get_peak_usage(false));

        return [
            'panel' => [
                'Current usage' => $current,
                'Peak usage'    => $peak,
            ],
            'badge' => $peak,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $unit  = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return round($value, 2) . $units[$unit];
    }
}
