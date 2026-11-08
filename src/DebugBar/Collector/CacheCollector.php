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

/**
 * Records cache operations. `addOperation()` takes the operation and the key.
 *
 * @phpstan-import-type list_envelope from DebugBarTypes
 * @phpstan-import-type list_panel from DebugBarTypes
 */
final class CacheCollector extends AbstractCollector
{
    use LimitsRows;

    public const NAME = 'cache';

    protected string $label = 'Cache';

    protected string $panel = 'list';

    /**
     * @var list_panel
     */
    private array $operations = [];

    public function __construct(private readonly int $maxRows = self::DEFAULT_MAX_ROWS)
    {
    }

    public function addOperation(string $operation, string $key): void
    {
        if (!$this->acceptsRow(count($this->operations))) {
            return;
        }

        $this->operations[] = [
            'label'   => $operation,
            'message' => $key,
        ];
    }

    /**
     * @return list_envelope
     */
    public function collect(): array
    {
        $rows = $this->operations;
        if (0 < $this->droppedRows) {
            $rows[] = $this->droppedRow();
        }

        return [
            'panel' => $rows,
            'badge' => count($this->operations) + $this->droppedRows,
        ];
    }
}
