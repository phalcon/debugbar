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

use Phalcon\DebugBar\Contracts\Renderable;
use Phalcon\DebugBar\DebugBarTypes;

/**
 * Optional base for collectors. Supplies the `NAME` wiring and `getWidget()`
 * scaffolding from protected members, so a subclass only sets its label, panel
 * type, and `collect()`.
 *
 * @phpstan-import-type widget from DebugBarTypes
 * @phpstan-import-type indicator from DebugBarTypes
 * @phpstan-import-type panel_type from DebugBarTypes
 */
abstract class AbstractCollector implements Renderable
{
    /**
     * Default row limit of the collectors that use `LimitsRows`
     */
    public const DEFAULT_MAX_ROWS = 1000;

    public const NAME = '';

    /**
     * @var indicator|null
     */
    protected ?array $indicator = null;

    protected string $label = '';

    /**
     * @var panel_type
     */
    protected string $panel = 'grid';

    public function getName(): string
    {
        /** @var string $name */
        $name = static::NAME;

        return $name;
    }

    /**
     * @return widget
     */
    public function getWidget(): array
    {
        $widget = [
            'label' => ('' !== $this->label) ? $this->label : $this->getName(),
            'panel' => $this->panel,
        ];

        if (null !== $this->indicator) {
            $widget['indicator'] = $this->indicator;
        }

        return $widget;
    }
}
