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

/**
 * Keeps the first `$maxRows` rows of a collector and counts the rows after them.
 * The class that uses this trait sets `$maxRows`. Its `collect()` adds the
 * dropped rows to the badge, and adds `droppedRow()` as the last row.
 */
trait LimitsRows
{
    private int $droppedRows = 0;

    /**
     * Returns true when the collector has fewer than `$maxRows` rows. If not, it
     * counts one more dropped row and returns false.
     */
    private function acceptsRow(int $rows): bool
    {
        if ($rows < $this->maxRows) {
            return true;
        }

        $this->droppedRows++;

        return false;
    }

    /**
     * @return array{label: string, message: string}
     */
    private function droppedRow(): array
    {
        return [
            'label'   => 'dropped',
            'message' => $this->droppedRows . (1 === $this->droppedRows ? ' row dropped' : ' rows dropped'),
        ];
    }
}
