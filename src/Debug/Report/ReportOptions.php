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
 * Immutable snapshot of the render-configuration values that Debug passes to
 * ReportBuilder::build(). Replaces the former long positional argument list
 * with a single named parameter object.
 */
final class ReportOptions
{
    /**
     * @param array<array-key, mixed> $blacklist
     * @param array<array-key, mixed> $data
     */
    public function __construct(
        private readonly array $blacklist,
        private readonly bool $showBackTrace,
        private readonly bool $showFiles,
        private readonly bool $showFileFragment,
        private readonly string $uri,
        private readonly array $data,
    ) {
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getBlacklist(): array
    {
        return $this->blacklist;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getShowBackTrace(): bool
    {
        return $this->showBackTrace;
    }

    public function getShowFileFragment(): bool
    {
        return $this->showFileFragment;
    }

    public function getShowFiles(): bool
    {
        return $this->showFiles;
    }

    public function getUri(): string
    {
        return $this->uri;
    }
}
