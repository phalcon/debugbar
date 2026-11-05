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
    public const NAME = 'cache';

    protected string $icon = 'icon-cache';

    protected string $label = 'Cache';

    protected string $panel = 'list';

    /**
     * @var list_panel
     */
    private array $operations = [];

    public function addOperation(string $operation, string $key): void
    {
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
        return [
            'panel' => $this->operations,
            'badge' => count($this->operations),
        ];
    }
}
