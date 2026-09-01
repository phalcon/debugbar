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
 * Enables the inline request-history browser. The browser itself is rendered
 * by the JavaScript client; this collector only carries its internal endpoint.
 */
final class HistoryCollector extends AbstractCollector
{
    public const NAME = 'history';

    /**
     * @var string
     */
    protected string $icon = 'icon-history';

    /**
     * @var string
     */
    protected string $label = 'History';

    /**
     * @var string
     */
    protected string $panel = 'history';

    /**
     * @param string $url
     */
    public function __construct(private readonly string $url)
    {
    }

    /**
     * @return array{panel: array{url: string}, badge: null}
     */
    public function collect(): array
    {
        return [
            'panel' => ['url' => $this->url],
            'badge' => null,
        ];
    }
}
