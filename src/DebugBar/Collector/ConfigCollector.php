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

use Phalcon\Config\ConfigInterface;
use Phalcon\DebugBar\DebugBarTypes;
use Phalcon\DebugBar\Security\Redactor;

/**
 * Snapshots the application config into a redacted grid. The config object is
 * resolved once by the provider and injected here, so the collector never
 * touches the container; it reads `toArray()` at collect time.
 *
 * @phpstan-import-type grid_envelope from DebugBarTypes
 */
final class ConfigCollector extends AbstractCollector
{
    use FlattensToGrid;

    public const NAME = 'config';

    protected string $icon = 'icon-config';

    protected string $label = 'Config';

    protected string $panel = 'grid';

    public function __construct(
        private readonly ?ConfigInterface $config,
        private readonly Redactor $redactor
    ) {
    }

    /**
     * @return grid_envelope
     */
    public function collect(): array
    {
        if (null === $this->config) {
            return [
                'panel' => [],
                'badge' => null,
            ];
        }

        return [
            'panel' => $this->flatten($this->redactor->redact($this->config->toArray())),
            'badge' => null,
        ];
    }
}
