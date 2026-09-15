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

/**
 * Enables the inline request-history browser. The browser itself is rendered
 * by the JavaScript client; this collector only carries its internal endpoint.
 *
 * @phpstan-import-type widget from DebugBarTypes
 */
final class HistoryCollector extends AbstractCollector
{
    public const NAME = 'history';

    protected string $icon = 'icon-history';

    protected string $label = 'History';

    protected string $panel = 'history';

    public function __construct(
        private readonly string $url,
        private readonly ?string $method = null,
        private readonly ?string $uri = null
    ) {
    }

    /**
     * @return array{
     *     panel: array{url: string, method?: string, uri?: string},
     *     badge: null
     * }
     */
    public function collect(): array
    {
        $panel = ['url' => $this->url];
        if (null !== $this->method) {
            $panel['method'] = $this->method;
        }
        if (null !== $this->uri) {
            $panel['uri'] = $this->uri;
        }

        return [
            'panel' => $panel,
            'badge' => null,
        ];
    }

    /**
     * @return widget
     */
    public function getWidget(): array
    {
        return [
            ...parent::getWidget(),
            'indicators' => [
                [
                    'collector' => TimeCollector::NAME,
                    'icon'      => 'clock',
                    'label'     => 'Request time',
                    'path'      => ['badge'],
                ],
                [
                    'collector' => MemoryCollector::NAME,
                    'icon'      => 'cogs',
                    'label'     => 'Current memory usage',
                    'path'      => ['metrics', MemoryCollector::METRIC_CURRENT_USAGE],
                ],
            ],
        ];
    }
}
