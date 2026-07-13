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

use Phalcon\Support\Version;

use const PHP_VERSION;

/**
 * Reports the running PHP and Phalcon versions.
 *
 * @phpstan-import-type grid_envelope from \Phalcon\DebugBar\DebugBarTypes
 */
final class VersionCollector extends AbstractCollector
{
    public const NAME = 'version';

    /**
     * @var string
     */
    protected string $icon = 'icon-version';

    /**
     * @var string
     */
    protected string $label = 'Version';

    /**
     * @var string
     */
    protected string $panel = 'grid';

    /**
     * @return grid_envelope
     */
    public function collect(): array
    {
        return [
            'panel' => [
                'PHP'     => PHP_VERSION,
                'Phalcon' => (new Version())->get(),
            ],
            'badge' => null,
        ];
    }
}
