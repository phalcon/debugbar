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

use Closure;
use Phalcon\DebugBar\DebugBarTypes;

/**
 * Reports the matched route. `setRoute()` takes the panel values, or a closure
 * that returns them when the values are not final until `collect()`.
 *
 * @phpstan-import-type grid_envelope from DebugBarTypes
 * @phpstan-import-type grid_panel from DebugBarTypes
 */
final class RouteCollector extends AbstractCollector
{
    public const NAME = 'route';

    protected string $icon = 'icon-route';

    protected string $label = 'Route';

    protected string $panel = 'grid';

    /**
     * @var (Closure(): grid_panel)|grid_panel
     */
    private array | Closure $route = [];

    /**
     * @return grid_envelope
     */
    public function collect(): array
    {
        return [
            'panel' => $this->route instanceof Closure ? ($this->route)() : $this->route,
            'badge' => null,
        ];
    }

    /**
     * Takes the panel values, or a closure that returns them. The collector calls
     * the closure at `collect()`.
     *
     * @param (Closure(): grid_panel)|grid_panel $route
     */
    public function setRoute(array | Closure $route): void
    {
        $this->route = $route;
    }
}
