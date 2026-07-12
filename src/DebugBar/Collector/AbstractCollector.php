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

/**
 * Optional base for collectors. Supplies the `NAME` wiring and `getWidget()`
 * scaffolding from three protected members, so a subclass only sets its icon,
 * label, panel type, and `collect()`.
 */
abstract class AbstractCollector implements Renderable
{
    public const NAME = '';

    /**
     * @var string
     */
    protected string $icon = '';

    /**
     * @var string
     */
    protected string $label = '';

    /**
     * @var string
     */
    protected string $panel = 'grid';

    /**
     * @return string
     */
    public function getName(): string
    {
        /** @var string $name */
        $name = static::NAME;

        return $name;
    }

    /**
     * @return array{label: string, icon: string, panel: string}
     */
    public function getWidget(): array
    {
        return [
            'label' => ('' !== $this->label) ? $this->label : $this->getName(),
            'icon'  => $this->icon,
            'panel' => $this->panel,
        ];
    }
}
