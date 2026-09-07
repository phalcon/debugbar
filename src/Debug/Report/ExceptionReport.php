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

namespace Phalcon\Debug\Report;

/**
 * Carries all data collected for an exception, ready to be rendered. Holds no
 * presentation logic.
 */
final class ExceptionReport
{
    /**
     * @var list<BacktraceItem>
     */
    private array $backtrace = [];

    /**
     * @var list<string>
     */
    private array $includedFiles = [];

    private int $memoryUsage = 0;

    private int $peakMemoryUsage = 0;

    /**
     * @var array<array-key, mixed>
     */
    private array $request = [];

    /**
     * @var array<array-key, mixed>
     */
    private array $server = [];

    /**
     * @var array<array-key, mixed>
     */
    private array $variables = [];

    public function __construct(
        private readonly string $className,
        private readonly string $message,
        private readonly string $file,
        private readonly int $line,
        private readonly bool $showBackTrace,
        private readonly string $uri,
    ) {
    }

    /**
     * @return list<BacktraceItem>
     */
    public function getBacktrace(): array
    {
        return $this->backtrace;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * @return list<string>
     */
    public function getIncludedFiles(): array
    {
        return $this->includedFiles;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getMemoryUsage(): int
    {
        return $this->memoryUsage;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getPeakMemoryUsage(): int
    {
        return $this->peakMemoryUsage;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getServer(): array
    {
        return $this->server;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    public function hasVariables(): bool
    {
        return !empty($this->variables);
    }

    public function isShowBackTrace(): bool
    {
        return $this->showBackTrace;
    }

    /**
     * @param list<BacktraceItem> $backtrace
     */
    public function setBacktrace(array $backtrace): static
    {
        $this->backtrace = $backtrace;

        return $this;
    }

    /**
     * @param list<string> $includedFiles
     */
    public function setIncludedFiles(array $includedFiles): static
    {
        $this->includedFiles = $includedFiles;

        return $this;
    }

    public function setMemoryUsage(int $memoryUsage): static
    {
        $this->memoryUsage = $memoryUsage;

        return $this;
    }

    public function setPeakMemoryUsage(int $peakMemoryUsage): static
    {
        $this->peakMemoryUsage = $peakMemoryUsage;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $request
     */
    public function setRequest(array $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $server
     */
    public function setServer(array $server): static
    {
        $this->server = $server;

        return $this;
    }

    /**
     * @param array<array-key, mixed> $variables
     */
    public function setVariables(array $variables): static
    {
        $this->variables = $variables;

        return $this;
    }
}
