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

namespace Phalcon\DebugBar;

use Phalcon\DebugBar\Contracts\Collector;
use Phalcon\DebugBar\Exceptions\Exception;

use function count;

/**
 * Aggregates registered collectors into a single per-request payload. The
 * registry is keyed by each collector's name, so registering a collector whose
 * name already exists replaces the previous one (last-write-wins).
 */
class DebugBar
{
    /**
     * @var array<string, Collector>
     */
    protected array $collectors = [];

    /**
     * @var array{
     *     data: array<string, array{panel: mixed, badge: scalar|null}>,
     *     meta: array<string, mixed>
     * }
     */
    protected array $data = [
        'data' => [],
        'meta' => [],
    ];

    /**
     * Registers (or replaces) a collector under its name.
     *
     * @param Collector $collector
     *
     * @return static
     */
    public function addCollector(Collector $collector): static
    {
        $this->collectors[$collector->getName()] = $collector;

        return $this;
    }

    /**
     * Runs every collector and caches the aggregated payload.
     *
     * @return array{
     *     data: array<string, array{panel: mixed, badge: scalar|null}>,
     *     meta: array<string, mixed>
     * }
     */
    public function collect(): array
    {
        $data = [];
        foreach ($this->collectors as $name => $collector) {
            $data[$name] = $collector->collect();
        }

        $this->data = [
            'data' => $data,
            'meta' => [
                'collectors' => count($data),
            ],
        ];

        return $this->data;
    }

    /**
     * @param string $name
     *
     * @return Collector
     * @throws Exception when no collector is registered under $name
     */
    public function getCollector(string $name): Collector
    {
        if (!isset($this->collectors[$name])) {
            throw new Exception('Unknown collector: ' . $name);
        }

        return $this->collectors[$name];
    }

    /**
     * @return array<string, Collector>
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    /**
     * Returns the last aggregated payload (empty until collect() has run).
     *
     * @return array{
     *     data: array<string, array{panel: mixed, badge: scalar|null}>,
     *     meta: array<string, mixed>
     * }
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasCollector(string $name): bool
    {
        return isset($this->collectors[$name]);
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function removeCollector(string $name): static
    {
        unset($this->collectors[$name]);

        return $this;
    }
}
