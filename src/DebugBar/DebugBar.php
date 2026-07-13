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
use Phalcon\DebugBar\Contracts\ExceptionAware;
use Phalcon\DebugBar\Contracts\MessageAware;
use Phalcon\DebugBar\Contracts\TimeAware;
use Phalcon\DebugBar\Exceptions\Exception;
use Throwable;

use function count;

/**
 * Aggregates registered collectors into a single per-request payload. The
 * registry is keyed by each collector's name, so registering a collector whose
 * name already exists replaces the previous one (last-write-wins).
 *
 * The convenience methods (`message()`, `startMeasure()`, `addException()`, …)
 * delegate to the `messages`/`time`/`exceptions` collectors when present and
 * no-op otherwise, so disabling a collector can never break calling code.
 *
 * @phpstan-import-type payload from DebugBarTypes
 */
class DebugBar
{
    /**
     * @var array<string, Collector>
     */
    protected array $collectors = [];

    /**
     * @var payload
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
     * @param Throwable $throwable
     *
     * @return static
     */
    public function addException(Throwable $throwable): static
    {
        $collector = $this->collectors['exceptions'] ?? null;
        if ($collector instanceof ExceptionAware) {
            $collector->addThrowable($throwable);
        }

        return $this;
    }

    /**
     * Runs every collector and caches the aggregated payload.
     *
     * @return payload
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
     * @param mixed ...$args
     *
     * @return static
     */
    public function debug(mixed ...$args): static
    {
        foreach ($args as $arg) {
            $this->message($arg, 'debug');
        }

        return $this;
    }

    /**
     * @param mixed ...$args
     *
     * @return static
     */
    public function error(mixed ...$args): static
    {
        foreach ($args as $arg) {
            $this->message($arg, 'error');
        }

        return $this;
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
     * @return payload
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
     * @param mixed ...$args
     *
     * @return static
     */
    public function info(mixed ...$args): static
    {
        foreach ($args as $arg) {
            $this->message($arg, 'info');
        }

        return $this;
    }

    /**
     * @param mixed  $message
     * @param string $label
     *
     * @return static
     */
    public function message(mixed $message, string $label = 'info'): static
    {
        $collector = $this->collectors['messages'] ?? null;
        if ($collector instanceof MessageAware) {
            $collector->addMessage($message, $label);
        }

        return $this;
    }

    /**
     * @param mixed ...$args
     *
     * @return static
     */
    public function notice(mixed ...$args): static
    {
        foreach ($args as $arg) {
            $this->message($arg, 'notice');
        }

        return $this;
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

    /**
     * @param string      $name
     * @param string|null $label
     *
     * @return static
     */
    public function startMeasure(string $name, ?string $label = null): static
    {
        $collector = $this->collectors['time'] ?? null;
        if ($collector instanceof TimeAware) {
            $collector->startMeasure($name, $label);
        }

        return $this;
    }

    /**
     * @param string $name
     *
     * @return static
     */
    public function stopMeasure(string $name): static
    {
        $collector = $this->collectors['time'] ?? null;
        if ($collector instanceof TimeAware) {
            $collector->stopMeasure($name);
        }

        return $this;
    }

    /**
     * @param mixed ...$args
     *
     * @return static
     */
    public function warning(mixed ...$args): static
    {
        foreach ($args as $arg) {
            $this->message($arg, 'warning');
        }

        return $this;
    }
}
