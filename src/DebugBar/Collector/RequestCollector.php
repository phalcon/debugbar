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

use Phalcon\DebugBar\Security\Redactor;
use Phalcon\Http\RequestInterface;

use function is_array;

/**
 * Snapshots the incoming request — method, URI, query, post, and headers —
 * redacting sensitive keys and flattening the nested arrays into a single grid.
 * The request object is resolved once by the provider and injected here, so the
 * collector reads it directly rather than the superglobals.
 *
 * @phpstan-import-type grid_envelope from \Phalcon\DebugBar\DebugBarTypes
 */
final class RequestCollector extends AbstractCollector
{
    use FlattensToGrid;

    public const NAME = 'request';

    /**
     * @var string
     */
    protected string $icon = 'icon-request';

    /**
     * @var string
     */
    protected string $label = 'Request';

    /**
     * @var string
     */
    protected string $panel = 'grid';

    /**
     * @param RequestInterface|null $request
     * @param Redactor              $redactor
     */
    public function __construct(
        private readonly ?RequestInterface $request,
        private readonly Redactor $redactor
    ) {
    }

    /**
     * @return grid_envelope
     */
    public function collect(): array
    {
        if (null === $this->request) {
            return [
                'panel' => [],
                'badge' => null,
            ];
        }

        $grid = [
            'Method' => $this->request->getMethod(),
            'URI'    => $this->request->getURI(),
        ];

        $sections = [
            'Query'   => $this->toArray($this->request->getQuery()),
            'Post'    => $this->toArray($this->request->getPost()),
            'Headers' => $this->request->getHeaders(),
        ];

        foreach ($sections as $label => $values) {
            foreach ($this->flatten($this->redactor->redact($values)) as $key => $value) {
                $grid[$label . '.' . $key] = $value;
            }
        }

        return [
            'panel' => $grid,
            'badge' => null,
        ];
    }

    /**
     * @param mixed $value
     *
     * @return array<array-key, mixed>
     */
    private function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
